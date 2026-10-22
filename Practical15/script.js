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
  body.dark { --paper:#0f1220; --card:#181d33; --line:#2c3350; --ink:#e8eaf6; --ink-soft:#a9aec6; }
  body.dark label, body.dark h3 { color:#c9d0ff; }
  body.dark tr:nth-child(even) td { background:#1d2340; }
  body.dark input, body.dark textarea, body.dark select { background:#11152a; color:#e8eaf6; }
  body.dark a { color:#9aa5ff; }
  body { transition: background 0.3s, color 0.3s; }

  button { transition: transform 0.2s; }
  button:active { transform: scale(0.95); }
  table, form, .faq-item { transition: transform 0.25s; }
  table:hover, form:hover, .faq-item:hover { transform: translateY(-3px); }

  .topbar { display:flex; align-items:center; gap:8px; background:#12162b; color:white; padding:8px 14px; }
  .topbar b { margin-right:auto; }
  .topbar button { margin:0; padding:8px 12px; background:#3a4060; box-shadow:none; color:white; }

  .menu { position:fixed; top:54px; left:0; bottom:0; width:220px; background:#181d33; padding:12px;
          transform:translateX(-100%); transition:transform 0.3s; z-index:10; }
  .menu.open { transform:translateX(0); }
  .menu a { display:block; color:white; padding:10px; text-decoration:none; }
  .menu a:hover { background:#d99a3d; color:black; }
  body > nav { display:none; }

  .banner { display:flex; background:#d99a3d; color:#1c2541; padding:10px 16px; max-height:80px; overflow:hidden;
            transition:max-height 0.3s, padding 0.3s; }
  .banner span { flex:1; text-align:center; }
  .banner button { margin:0; padding:2px 10px; box-shadow:none; background:#b97e29; }
  .banner.hide { max-height:0; padding-top:0; padding-bottom:0; }

  .overlay { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.6);
             display:flex; align-items:center; justify-content:center;
             opacity:0; visibility:hidden; transition:opacity 0.25s; z-index:20; }
  .overlay.show { opacity:1; visibility:visible; }
  .modal { background:var(--card); color:var(--ink); padding:24px; border-radius:12px; max-width:420px; }
  .modal h2 { margin-top:0; }

  .box { max-width:900px; margin:0 auto 24px; padding:0 20px; }
  .faq-item { background:var(--card); border:1px solid var(--line); border-radius:10px; margin-bottom:10px; overflow:hidden; }
  .faq-q { width:100%; text-align:left; background:none; color:var(--ink); box-shadow:none; border-radius:0; margin:0; padding:14px 18px; }
  .faq-a { max-height:0; overflow:hidden; transition:max-height 0.3s; padding:0 18px; color:var(--ink-soft); }
  .faq-item.open .faq-a { max-height:100px; padding-bottom:14px; }

  .slider { position:relative; overflow:hidden; background:var(--card); border:1px solid var(--line); border-radius:10px; }
  .track { display:flex; transition:transform 0.5s; }
  .slide { flex:0 0 100%; padding:36px 60px; text-align:center; }
  .prev, .next { position:absolute; top:40%; width:40px; height:40px; padding:0; border-radius:50%; }
  .prev { left:8px; }
  .next { right:8px; }
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
      '<button class="faq-q">' + faqs[i][0] + " +</button>" +
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
      this.parentElement.classList.toggle("open");
    });
  }
}


function setFieldError(input, errorId, message) {
  var error = document.getElementById(errorId);
  if (error) error.textContent = message;
  if (input) {
    if (message) input.setAttribute("aria-invalid", "true");
    else input.removeAttribute("aria-invalid");
  }
}

function passwordStrength(value) {
  var score = 0;
  if (value.length >= 8) score++;
  if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
  if (/[0-9]/.test(value)) score++;
  if (/[^A-Za-z0-9]/.test(value)) score++;
  return score;
}

function setupRegistrationValidation() {
  var form = document.getElementById("registerForm");
  if (!form) return;

  var fields = {
    fullName: { id: "nameError", check: function (v) { return /^[A-Za-z][A-Za-z .'-]{1,49}$/.test(v.trim()) ? "" : "Enter a valid name (at least 2 letters)."; } },
    enrollment: { id: "enrollmentError", check: function (v) { return /^[A-Za-z0-9-]{3,20}$/.test(v.trim()) ? "" : "Use 3–20 letters, numbers or hyphens."; } },
    email: { id: "emailError", check: function (v) { return /^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/.test(v.trim()) ? "" : "Enter a valid email address."; } },
    mobile: { id: "mobileError", check: function (v) { return /^[6-9][0-9]{9}$/.test(v.trim()) ? "" : "Enter a valid 10-digit Indian mobile number."; } },
    password: { id: "passwordError", check: function (v) { return /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9\s])\S{8,}$/.test(v) ? "" : "Use 8+ characters with uppercase, lowercase, number and symbol."; } },
    confirmPassword: { id: "confirmError", check: function (v) { return v && v === document.getElementById("password").value ? "" : "Passwords do not match."; } },
    course: { id: "courseError", check: function (v) { return v ? "" : "Please select a course."; } },
    year: { id: "yearError", check: function (v) { return /^[1-4]$/.test(v) ? "" : "Please select your year of study."; } }
  };

  function validate(name) {
    var input = form.elements[name];
    var message = fields[name].check(input.value);
    setFieldError(input, fields[name].id, message);
    return !message;
  }

  Object.keys(fields).forEach(function (name) {
    var input = form.elements[name];
    input.addEventListener("input", function () {
      validate(name);
      if (name === "password") updateStrength();
      if (name === "password" && form.elements.confirmPassword.value) validate("confirmPassword");
    });
    input.addEventListener("change", function () { validate(name); });
  });

  function updateStrength() {
    var value = form.elements.password.value;
    var score = passwordStrength(value);
    var bar = document.getElementById("strengthBar");
    var text = document.getElementById("strengthText");
    var labels = ["Not entered", "Weak", "Fair", "Good", "Strong"];
    bar.style.width = (score * 25) + "%";
    bar.className = score <= 1 ? "weak" : score === 2 ? "fair" : score === 3 ? "good" : "strong";
    text.textContent = "Password strength: " + (value ? labels[score] : labels[0]);
  }

  form.querySelectorAll('input[name="gender"]').forEach(function (radio) {
    radio.addEventListener("change", function () {
      setFieldError(null, "genderError", "");
    });
  });
  form.elements.terms.addEventListener("change", function () {
    setFieldError(form.elements.terms, "termsError", "");
  });

  form.addEventListener("submit", function (event) {
    event.preventDefault();
    var valid = true;
    Object.keys(fields).forEach(function (name) {
      if (!validate(name)) valid = false;
    });
    var gender = form.querySelector('input[name="gender"]:checked');
    setFieldError(null, "genderError", gender ? "" : "Please select your gender.");
    if (!gender) valid = false;
    var terms = form.elements.terms;
    setFieldError(terms, "termsError", terms.checked ? "" : "You must accept the terms to continue.");
    if (!terms.checked) valid = false;

    var message = document.getElementById("registerMessage");
    if (valid) {
      if (message) {
        message.textContent = "Validation passed! Submitting to Practical 9 secure registration...";
        message.className = "form-message success";
      }
      form.submit();
    } else {
      if (message) {
        message.textContent = "Please correct the highlighted fields and try again.";
        message.className = "form-message failure";
      }
      var firstInvalid = form.querySelector('[aria-invalid="true"]');
      if (firstInvalid) firstInvalid.focus();
    }
  });

  form.addEventListener("reset", function () {
    window.setTimeout(function () {
      form.querySelectorAll(".error").forEach(function (item) { item.textContent = ""; });
      form.querySelectorAll("[aria-invalid]").forEach(function (item) { item.removeAttribute("aria-invalid"); });
      var message = document.getElementById("registerMessage");
      message.textContent = "";
      message.className = "form-message";
      updateStrength();
    }, 0);
  });
  updateStrength();
}

function setupLoginValidation() {
  var form = document.getElementById("loginForm");
  if (!form) return;
  var loginId = document.getElementById("loginId");
  var password = document.getElementById("loginPassword");

  function validateId() {
    var value = loginId.value.trim();
    var valid = value.length > 0 && (value.indexOf("@") === -1 || /^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/.test(value));
    setFieldError(loginId, "loginIdError", valid ? "" : "Enter your student ID or a valid email address.");
    return valid;
  }
  function validatePassword() {
    var valid = password.value.length > 0;
    setFieldError(password, "loginPasswordError", valid ? "" : "Please enter your password.");
    return valid;
  }
  loginId.addEventListener("input", validateId);
  password.addEventListener("input", validatePassword);

  form.querySelectorAll(".show-password").forEach(function (button) {
    button.addEventListener("click", function () {
      var input = document.getElementById(button.getAttribute("data-target"));
      var showing = input.type === "password";
      input.type = showing ? "text" : "password";
      button.textContent = showing ? "Hide" : "Show";
      button.setAttribute("aria-label", showing ? "Hide password" : "Show password");
    });
  });

  form.addEventListener("submit", function (event) {
    var valid = validateId() && validatePassword();
    var message = document.getElementById("loginMessage");
    if (valid) {
      if (message) {
        message.textContent = "Credentials validated. Signing into Practical 9...";
        message.className = "form-message success";
      }
      form.submit();
    } else {
      event.preventDefault();
      if (message) {
        message.textContent = "Please correct the highlighted fields and try again.";
        message.className = "form-message failure";
      }
      var firstInvalid = form.querySelector('[aria-invalid="true"]');
      if (firstInvalid) firstInvalid.focus();
    }
  });

  form.addEventListener("reset", function () {
    window.setTimeout(function () {
      form.querySelectorAll(".error").forEach(function (item) { item.textContent = ""; });
      form.querySelectorAll("[aria-invalid]").forEach(function (item) { item.removeAttribute("aria-invalid"); });
      var message = document.getElementById("loginMessage");
      message.textContent = "";
      message.className = "form-message";
    }, 0);
  });

  var forgot = document.getElementById("forgotLink");
  forgot.addEventListener("click", function (event) {
    event.preventDefault();
    document.getElementById("loginMessage").textContent = "Please contact your administrator to reset your password.";
    document.getElementById("loginMessage").className = "form-message";
  });
}


window.addEventListener("DOMContentLoaded", function () {
  addStyles();
  var bar = addTopBar();
  setupMenu(bar);
  setupTheme();
  setupBanner(bar);
  setupModal();
  setupRegistrationValidation();
  setupLoginValidation();

  var table = document.querySelector("table");
  if (document.title === "Student Hub Portal" && table) {
    var sliderBox = setupSlider(table);
    setupFaq(sliderBox);
  }
});
