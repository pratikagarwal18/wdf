function togglePassword() {
  var passwordField = document.getElementById("passwordField");
  var toggleButton = document.getElementById("toggleButton");

  if (passwordField.type === "password") {
    passwordField.type = "text";
    toggleButton.textContent = "Hide";
  } else {
    passwordField.type = "password";
    toggleButton.textContent = "Show";
  }
}

var styles = `
  body.dark {
    --paper: #0f1220;
    --card: #181d33;
    --line: #2c3350;
    --ink: #e8eaf6;
    --ink-soft: #a9aec6;
    background: radial-gradient(1100px 500px at 100% -10%, #1a2244 0%, transparent 55%), #0f1220 !important;
  }
  body.dark label, body.dark h3 { color: #c9d0ff; }
  body.dark tr:nth-child(even) td { background: #1d2340; }
  body.dark tr:hover td { background: #252d50; }
  body.dark input, body.dark textarea, body.dark select {
    background: #11152a;
    color: #e8eaf6;
    border-color: #2c3350;
  }
  body.dark a { color: #9aa5ff; }
  body.dark .slider { background: #181d33; border-color: #2c3350; }
  body.dark .slide h3 { color: #dcdffd; }
  body.dark .slide p { color: #a9aec6; }
  body.dark .faq-item { background: #181d33; border-color: #2c3350; }
  body.dark .faq-q { color: #e8eaf6; }
  body.dark .faq-a { color: #a9aec6; }
  body { transition: background 0.3s, color 0.3s; }

  button { transition: transform 0.2s; }
  button:active { transform: scale(0.95); }
  table, form, .faq-item { transition: transform 0.25s; }
  table:hover, form:hover, .faq-item:hover { transform: translateY(-3px); }

  .topbar { display:flex; align-items:center; gap:8px; background:#12162b; color:white; padding:8px 14px; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 10px rgba(0,0,0,0.15); }
  .topbar b { margin-right:auto; font-size: 1.05rem; }
  .topbar button { margin:0; padding:8px 14px; background:#3a4060; box-shadow:none; color:white; border-radius: 6px; border: none; cursor: pointer; font-weight: 500; }
  .topbar button:hover { background:#4f5882; }

  .menu { display: block !important; position: fixed; top: 52px; left: 0; bottom: 0; width: 240px; background: #181d33; padding: 16px 12px;
          transform: translateX(-100%); transition: transform 0.3s ease; z-index: 90; box-shadow: 4px 0 16px rgba(0,0,0,0.35); }
  .menu.open { display: block !important; transform: translateX(0) !important; }
  .menu a { display: block; color: #e2e5f5; padding: 10px 14px; text-decoration: none; border-radius: 6px; margin-bottom: 4px; font-weight: 500; font-size: 0.95rem; text-transform: capitalize; transition: 0.2s; }
  .menu a:hover { background: #d99a3d; color: #12162b; font-weight: 600; padding-left: 18px; }
  body > nav:not(.menu) { display: none; }

  .banner { display:flex; align-items: center; background:#d99a3d; color:#1c2541; padding:10px 16px; max-height:80px; overflow:hidden;
            transition:max-height 0.3s, padding 0.3s; font-weight: 500; }
  .banner span { flex:1; text-align:center; }
  .banner button { margin:0; padding:4px 12px; box-shadow:none; background:#b97e29; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
  .banner.hide { max-height:0; padding-top:0; padding-bottom:0; }

  .overlay { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.6);
             display:flex; align-items:center; justify-content:center;
             opacity:0; visibility:hidden; transition:opacity 0.25s; z-index:120; }
  .overlay.show { opacity:1; visibility:visible; }
  .modal { background:var(--card); color:var(--ink); padding:24px; border-radius:12px; max-width:420px; box-shadow: 0 12px 32px rgba(0,0,0,0.25); }
  .modal h2 { margin-top:0; }

  .box { max-width:900px; margin:0 auto 28px; padding:0 20px; }
  .faq-item { background:var(--card); border:1px solid var(--line); border-radius:10px; margin-bottom:12px; overflow:hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.04); }
  .faq-q { width:100%; text-align:left; background:none; color:var(--ink); box-shadow:none; border-radius:0; margin:0; padding:16px 20px; font-weight: 600; font-size: 1rem; border: none; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
  .faq-q:hover { color: var(--blue); }
  .faq-a { max-height:0; overflow:hidden; transition:max-height 0.35s ease, padding 0.35s ease; padding:0 20px; color:var(--ink-soft); }
  .faq-item.open .faq-a { max-height:120px; padding-bottom:16px; }
  .faq-icon { font-weight: bold; font-size: 1.25rem; color: var(--gold-deep); line-height: 1; }

  .slider { position:relative; overflow:hidden; background:var(--card); border:1px solid var(--line); border-radius:10px; box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
  .track { display:flex; transition:transform 0.5s ease-in-out; }
  .slide { flex:0 0 100%; padding:36px 60px; text-align:center; }
  .slide h3 { margin: 0 0 10px 0; font-size: 1.3rem; color: var(--navy); }
  .prev, .next { position:absolute; top:50%; transform: translateY(-50%); width:42px; height:42px; padding:0; border-radius:50%; background: #3547d6 !important; color: white !important; border: none; font-size: 22px; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(53, 71, 214, 0.4); z-index: 5; transition: 0.2s; }
  .prev:hover, .next:hover { background: #4f5fe0 !important; transform: translateY(-50%) scale(1.08); }
  .prev { left:12px; }
  .next { right:12px; }

  /* Password toggle & input wrapper */
  .password-wrapper { display: flex; align-items: center; gap: 8px; max-width: 320px; margin-bottom: 6px; }
  .password-wrapper input { flex: 1; margin: 0; }
  #toggleButton { padding: 9px 18px; background: #3547d6 !important; color: white !important; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.9rem; margin: 0; box-shadow: 0 2px 8px rgba(53,71,214,0.3); }
  #toggleButton:hover { background: #4f5fe0 !important; }

  /* Notification banner for wrong password / authentication */
  .notification-alert {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 16px;
    border-radius: 8px;
    margin-bottom: 18px;
    font-size: 0.95rem;
    line-height: 1.45;
    box-shadow: 0 4px 14px rgba(220, 38, 38, 0.12);
  }
  .notification-alert.error {
    background: #fef2f2;
    border: 1.5px solid #ef4444;
    color: #991b1b;
  }
  .notification-alert.success {
    background: #f0fdf4;
    border: 1.5px solid #22c55e;
    color: #166534;
  }
  body.dark .notification-alert.error {
    background: #381515;
    border-color: #f87171;
    color: #fca5a5;
  }
  body.dark .notification-alert.success {
    background: #142e1d;
    border-color: #4ade80;
    color: #86efac;
  }
  .notification-alert .notif-icon {
    font-size: 1.4rem;
    line-height: 1;
  }
  .notification-alert .notif-body strong {
    display: block;
    font-size: 1rem;
    margin-bottom: 2px;
  }
  .shake {
    animation: shakeAnim 0.4s ease-in-out;
  }
  @keyframes shakeAnim {
    0%, 100% { transform: translateX(0); }
    20%, 60% { transform: translateX(-8px); }
    40%, 80% { transform: translateX(8px); }
  }
`;

function addStyles() {
  var styleTag = document.createElement("style");
  styleTag.textContent = styles;
  document.head.appendChild(styleTag);
}

function addTopBar() {
  var bar = document.createElement("header");
  bar.className = "topbar";
  bar.innerHTML =
    '<button id="burger">☰</button>' +
    "<b>Student Hub</b>" +
    '<button id="helpBtn">? Help</button>' +
    '<button id="themeBtn"></button>';
  document.body.prepend(bar);
  return bar;
}

function setupMenu(bar) {
  var pages = ["index", "login", "register", "dashboard", "profile", "courses",
               "attendance", "assignment", "result", "contact", "feedback"];
  var links = "";
  for (var i = 0; i < pages.length; i++) {
    links += '<a href="' + pages[i] + '.html">' + pages[i] + "</a>";
  }

  var menu = document.createElement("nav");
  menu.className = "menu";
  menu.innerHTML = links;
  bar.after(menu);

  document.getElementById("burger").addEventListener("click", function () {
    menu.classList.toggle("open");
  });

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
      menu.classList.remove("open");
    }
  });
}

function setupTheme() {
  var themeBtn = document.getElementById("themeBtn");

  if (localStorage.getItem("theme") === "dark") {
    document.body.classList.add("dark");
  }

  function showIcon() {
    if (document.body.classList.contains("dark")) {
      themeBtn.textContent = "☀️";
    } else {
      themeBtn.textContent = "🌙";
    }
  }
  showIcon();

  themeBtn.addEventListener("click", function () {
    document.body.classList.toggle("dark");

    if (document.body.classList.contains("dark")) {
      localStorage.setItem("theme", "dark");
    } else {
      localStorage.setItem("theme", "light");
    }
    showIcon();
  });
}

function setupBanner(bar) {
  if (localStorage.getItem("bannerClosed") === "yes") {
    return;
  }

  var banner = document.createElement("div");
  banner.className = "banner";
  banner.innerHTML =
    "<span>📢 Course registration closes on 30th November.</span>" +
    "<button>×</button>";
  bar.after(banner);

  banner.querySelector("button").addEventListener("click", function () {
    banner.classList.add("hide");
    localStorage.setItem("bannerClosed", "yes");
  });
}

function setupModal() {
  var overlay = document.createElement("div");
  overlay.className = "overlay";
  overlay.innerHTML =
    '<div class="modal">' +
    "<h2>Quick Help</h2>" +
    "<p>Use the ☰ menu to open any page and the 🌙 button to change the theme.</p>" +
    '<button id="closeModal">Close</button>' +
    "</div>";
  document.body.appendChild(overlay);

  document.getElementById("helpBtn").addEventListener("click", function () {
    overlay.classList.add("show");
  });

  document.getElementById("closeModal").addEventListener("click", function () {
    overlay.classList.remove("show");
  });

  overlay.addEventListener("click", function (event) {
    if (event.target === overlay) {
      overlay.classList.remove("show");
    }
  });

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
      overlay.classList.remove("show");
    }
  });
}

function setupSlider(table) {
  var slides = [
    ["Examinations", "Semester examinations begin on 10th December."],
    ["Registration", "Course registration closes on 30th November."],
    ["Attendance", "Keep your attendance above 75%."],
    ["Library", "Open from 8:00 AM to 8:00 PM."]
  ];

  var slidesHtml = "";
  for (var i = 0; i < slides.length; i++) {
    slidesHtml += '<div class="slide"><h3>' + slides[i][0] + "</h3><p>" + slides[i][1] + "</p></div>";
  }

  var box = document.createElement("section");
  box.className = "box";
  box.innerHTML =
    "<h2>Highlights</h2>" +
    '<div class="slider">' +
    '<div class="track">' + slidesHtml + "</div>" +
    '<button class="prev">‹</button>' +
    '<button class="next">›</button>' +
    "</div>";
  table.after(box);

  var track = box.querySelector(".track");
  var current = 0;

  function showSlide(number) {
    if (number < 0) {
      number = slides.length - 1;
    }
    if (number >= slides.length) {
      number = 0;
    }
    current = number;
    track.style.transform = "translateX(-" + current * 100 + "%)";
  }

  box.querySelector(".prev").addEventListener("click", function () {
    showSlide(current - 1);
  });

  box.querySelector(".next").addEventListener("click", function () {
    showSlide(current + 1);
  });

  setInterval(function () {
    showSlide(current + 1);
  }, 5000);

  return box;
}

function setupFaq(afterElement) {
  var faqs = [
    ["How do I register for courses?", "Open the Register page and fill in your details."],
    ["What attendance is required?", "You must keep your attendance above 75%."],
    ["When do exams begin?", "Semester examinations begin on 10th December."]
  ];

  var faqHtml = "";
  for (var i = 0; i < faqs.length; i++) {
    faqHtml +=
      '<div class="faq-item">' +
      '<button class="faq-q">' +
      '<span>' + faqs[i][0] + '</span>' +
      '<span class="faq-icon">+</span>' +
      '</button>' +
      '<div class="faq-a"><p>' + faqs[i][1] + "</p></div>" +
      "</div>";
  }

  var box = document.createElement("section");
  box.className = "box";
  box.innerHTML = "<h2>FAQ</h2>" + faqHtml;
  afterElement.after(box);

  var questions = box.querySelectorAll(".faq-q");
  for (var j = 0; j < questions.length; j++) {
    questions[j].addEventListener("click", function () {
      var item = this.parentElement;
      var icon = this.querySelector(".faq-icon");
      item.classList.toggle("open");
      if (item.classList.contains("open")) {
        if (icon) icon.textContent = "−";
      } else {
        if (icon) icon.textContent = "+";
      }
    });
  }
}

function setupLoginValidation() {
  var loginForm = document.getElementById("loginForm") || document.querySelector("form");
  var pwdField = document.getElementById("passwordField");
  if (!loginForm || !pwdField) return;

  var notif = document.getElementById("loginNotification");
  if (!notif) {
    notif = document.createElement("div");
    notif.id = "loginNotification";
    loginForm.prepend(notif);
  }

  loginForm.addEventListener("submit", function (e) {
    e.preventDefault();
    var val = pwdField.value.trim();
    var validDemoPassword = "Student@123";

    if (val === "" || val !== validDemoPassword) {
      notif.className = "notification-alert error shake";
      notif.style.display = "flex";
      notif.innerHTML =
        '<div class="notif-icon">⚠️</div>' +
        '<div class="notif-body">' +
        '<strong>Authentication Failed</strong>' +
        '<span>Incorrect password! Access denied. Please enter the valid password and try again.</span>' +
        '</div>';
      pwdField.classList.add("shake");
      setTimeout(function () {
        pwdField.classList.remove("shake");
      }, 500);
    } else {
      notif.className = "notification-alert success";
      notif.style.display = "flex";
      notif.innerHTML =
        '<div class="notif-icon">✅</div>' +
        '<div class="notif-body">' +
        '<strong>Login Successful</strong>' +
        '<span>Welcome back! Authenticated successfully.</span>' +
        '</div>';
    }
  });
}

window.addEventListener("DOMContentLoaded", function () {
  addStyles();
  var bar = addTopBar();
  setupMenu(bar);
  setupTheme();
  setupBanner(bar);
  setupModal();
  setupLoginValidation();

  var table = document.querySelector("table");
  if (document.title === "Student Hub Portal" && table) {
    var sliderBox = setupSlider(table);
    setupFaq(sliderBox);
  }
});
