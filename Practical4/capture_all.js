const fs = require('fs');
const path = require('path');
const { spawn } = require('child_process');

const edgePath = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const wsScreenshotsDir = 'C:\\Users\\Pratik\\OneDrive\\Desktop\\wdf\\Practical4\\screenshots';
const brainDir = 'C:\\Users\\Pratik\\.gemini\\antigravity-ide\\brain\\8a47ba9a-9ee5-4a2c-811c-284d3d662dba';
const tempProfile = path.join(process.env.TEMP || 'C:\\Temp', 'edge_screen_' + Date.now());

fs.mkdirSync(wsScreenshotsDir, { recursive: true });
fs.mkdirSync(brainDir, { recursive: true });
fs.mkdirSync(tempProfile, { recursive: true });

function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

class CDPClient {
  constructor(wsUrl) {
    this.wsUrl = wsUrl;
    this.ws = null;
    this.id = 1;
    this.callbacks = new Map();
  }

  async connect() {
    return new Promise((resolve, reject) => {
      this.ws = new WebSocket(this.wsUrl);
      this.ws.onopen = () => resolve();
      this.ws.onerror = (err) => reject(err);
      this.ws.onmessage = (event) => {
        const msg = JSON.parse(event.data);
        if (msg.id && this.callbacks.has(msg.id)) {
          const { resolve, reject } = this.callbacks.get(msg.id);
          this.callbacks.delete(msg.id);
          if (msg.error) {
            reject(new Error(msg.error.message));
          } else {
            resolve(msg.result);
          }
        }
      };
    });
  }

  send(method, params = {}) {
    return new Promise((resolve, reject) => {
      const id = this.id++;
      this.callbacks.set(id, { resolve, reject });
      this.ws.send(JSON.stringify({ id, method, params }));
    });
  }

  async eval(expression) {
    const res = await this.send('Runtime.evaluate', {
      expression,
      returnByValue: true,
      awaitPromise: true
    });
    if (res.exceptionDetails) {
      console.error('eval exceptionDetails:', res.exceptionDetails);
      throw new Error(res.exceptionDetails.text || 'Eval error');
    }
    return res.result ? res.result.value : undefined;
  }

  async screenshot(filename, options = {}) {
    const res = await this.send('Page.captureScreenshot', {
      format: 'png',
      ...options
    });
    const buffer = Buffer.from(res.data, 'base64');
    const wsFile = path.join(wsScreenshotsDir, filename);
    const brainFile = path.join(brainDir, filename);
    fs.writeFileSync(wsFile, buffer);
    fs.writeFileSync(brainFile, buffer);
    console.log(`Saved screenshot: ${filename} (${buffer.length} bytes)`);
  }

  close() {
    if (this.ws) {
      this.ws.close();
    }
  }
}

async function run() {
  console.log('Starting Edge in headless mode...');
  const edge = spawn(edgePath, [
    '--headless=new',
    '--remote-debugging-port=9444',
    '--user-data-dir=' + tempProfile,
    '--window-size=1280,900',
    '--hide-scrollbars',
    '--force-device-scale-factor=1'
  ]);

  edge.stderr.on('data', () => {});

  await sleep(1500);

  try {
    const targetsRes = await fetch('http://127.0.0.1:9444/json/list');
    const targets = await targetsRes.json();
    const pageTarget = targets.find(t => t.type === 'page') || targets[0];
    if (!pageTarget) throw new Error('No page target found');
    console.log('Connecting to page target:', pageTarget.id);
    const client = new CDPClient(pageTarget.webSocketDebuggerUrl);
    await client.connect();

    await client.send('Page.enable');
    await client.send('Runtime.enable');

    console.log('--- 1. Login Page: Toggle Password (Hidden) ---');
    const loginUrl = 'file:///C:/Users/Pratik/OneDrive/Desktop/wdf/Practical4/login.html';
    await client.send('Page.navigate', { url: loginUrl });
    await sleep(800);

    await client.eval(`
      localStorage.setItem('theme', 'light');
      document.body.classList.remove('dark');
      document.getElementById('studentId').value = 'STU-2025-089';
      document.getElementById('passwordField').value = 'Student@Pass2025';
      document.getElementById('passwordField').type = 'password';
      document.getElementById('toggleButton').textContent = 'Show';
    `);
    await sleep(300);
    await client.screenshot('01_toggle_password_hidden.png');

    console.log('--- 2. Login Page: Toggle Password (Revealed) ---');
    await client.eval(`
      togglePassword();
    `);
    await sleep(300);
    await client.screenshot('02_toggle_password_revealed.png');

    console.log('--- 3. Login Page: Wrong Password Notification ---');
    await client.eval(`
      document.getElementById('passwordField').type = 'password';
      document.getElementById('passwordField').value = 'wrong_secret_123';
      document.getElementById('toggleButton').textContent = 'Show';
      // Trigger login submit
      const form = document.getElementById('loginForm');
      form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    `);
    await sleep(500);
    await client.screenshot('03_notification_wrong_password.png');

    console.log('--- 4. Portal Index: Light Theme ---');
    const indexUrl = 'file:///C:/Users/Pratik/OneDrive/Desktop/wdf/Practical4/index.html';
    await client.send('Page.navigate', { url: indexUrl });
    await sleep(800);
    await client.eval(`(() => {
      localStorage.setItem('theme', 'light');
      document.body.classList.remove('dark');
      const themeBtn = document.getElementById('themeBtn');
      if (themeBtn) themeBtn.textContent = '🌙';
      window.scrollTo(0, 0);
    })()`);
    await sleep(300);
    await client.screenshot('04_light_theme.png');

    console.log('--- 5. Portal Index: Dark Theme ---');
    await client.eval(`(() => {
      document.body.classList.add('dark');
      localStorage.setItem('theme', 'dark');
      const themeBtn = document.getElementById('themeBtn');
      if (themeBtn) themeBtn.textContent = '☀️';
      window.scrollTo(0, 0);
    })()`);
    await sleep(400);
    await client.screenshot('05_dark_theme.png');

    console.log('--- 6. Portal Index: Hamburger Menu ---');
    await client.eval(`(() => {
      document.body.classList.remove('dark');
      localStorage.setItem('theme', 'light');
      const themeBtn = document.getElementById('themeBtn');
      if (themeBtn) themeBtn.textContent = '🌙';
      const menu = document.querySelector('.menu');
      if (menu) menu.classList.add('open');
      window.scrollTo(0, 0);
    })()`);
    await sleep(400);
    await client.screenshot('06_hamburger_menu.png');

    console.log('--- 7. Portal Index: Slidebar (Highlights Slider) ---');
    await client.eval(`(() => {
      const menu = document.querySelector('.menu');
      if (menu) menu.classList.remove('open');
      const sliderBox = document.querySelectorAll('.box')[0];
      if (sliderBox) {
        sliderBox.scrollIntoView({ block: 'center', behavior: 'instant' });
      }
    })()`);
    await sleep(400);
    await client.screenshot('07_slidebar_slider.png');

    console.log('--- 8. Portal Index: Collapsible FAQ ---');
    await client.eval(`(() => {
      const faqBox = document.querySelectorAll('.box')[1];
      if (faqBox) {
        faqBox.scrollIntoView({ block: 'center', behavior: 'instant' });
      }
      const questions = document.querySelectorAll('.faq-q');
      if (questions.length > 0) {
        questions[0].click(); // Expand question 1
      }
      if (questions.length > 2) {
        questions[2].click(); // Expand question 3
      }
    })()`);
    await sleep(500);
    await client.screenshot('08_collapsible_faq.png');

    client.close();
    console.log('All screenshots captured successfully!');
  } catch (err) {
    console.error('Error during execution:', err);
  } finally {
    edge.kill();
  }
}

run();
