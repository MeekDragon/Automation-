<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CrossPublish - Sign In</title>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --font-family: 'Outfit', 'Inter', sans-serif;
      --bg-color: #05040a;
      --card-bg: rgba(14, 11, 28, 0.65);
      --card-border: rgba(255, 255, 255, 0.08);
      --text-primary: #FFFFFF;
      --text-secondary: #B4A8D3;
      --accent-primary: #FF007A;
      --accent-danger: #FF0055;
      --input-bg: rgba(7, 4, 18, 0.85);
      --input-border: rgba(255, 255, 255, 0.1);
      --neon-purple: #7928CA;
      --neon-cyan: #00F0FF;
    }
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    body {
      font-family: var(--font-family);
      background-color: var(--bg-color);
      color: var(--text-primary);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      position: relative;
    }
    /* Background blobs */
    .bg-blobs {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      z-index: -1;
      overflow: hidden;
    }
    .blob {
      position: absolute;
      border-radius: 50%;
      filter: blur(140px);
      opacity: 0.18;
    }
    .blob-1 {
      top: -15%;
      right: -5%;
      width: 450px;
      height: 450px;
      background: radial-gradient(circle, var(--accent-primary) 0%, var(--neon-purple) 60%, rgba(0,0,0,0) 100%);
    }
    .blob-2 {
      bottom: -15%;
      left: -5%;
      width: 500px;
      height: 500px;
      background: radial-gradient(circle, var(--neon-cyan) 0%, var(--neon-purple) 60%, rgba(0,0,0,0) 100%);
    }
    /* Glass card */
    .login-card {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 24px;
      padding: 40px;
      width: 90%;
      max-width: 420px;
      box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.45);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      text-align: center;
      transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }
    .login-card:hover {
      border-color: rgba(255, 0, 122, 0.2);
      box-shadow: 0 12px 40px 0 rgba(121, 40, 202, 0.15);
    }
    .logo-container {
      margin-bottom: 24px;
    }
    h1 {
      font-size: 2.2rem;
      font-weight: 800;
      letter-spacing: -0.04em;
      background: linear-gradient(135deg, var(--neon-cyan) 0%, var(--neon-purple) 50%, var(--accent-primary) 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      margin-bottom: 6px;
      text-shadow: 0 0 20px rgba(0, 240, 255, 0.2);
    }
    p {
      color: var(--text-secondary);
      font-size: 0.9rem;
      margin-bottom: 30px;
    }
    .form-group {
      text-align: left;
      margin-bottom: 20px;
    }
    label {
      display: block;
      font-size: 0.88rem;
      font-weight: 600;
      margin-bottom: 8px;
      color: var(--text-primary);
    }
    input {
      width: 100%;
      background: var(--input-bg);
      border: 1px solid var(--input-border);
      border-radius: 12px;
      padding: 14px 16px;
      color: var(--text-primary);
      font-family: var(--font-family);
      font-size: 0.95rem;
      transition: all 0.25s ease;
    }
    input:focus {
      outline: none;
      border-color: var(--neon-cyan);
      box-shadow: 0 0 12px rgba(0, 240, 255, 0.35);
    }
    .btn {
      width: 100%;
      background: linear-gradient(135deg, var(--neon-purple) 0%, var(--accent-primary) 50%, var(--neon-cyan) 100%);
      background-size: 200% auto;
      color: white;
      border: none;
      padding: 14px;
      font-family: var(--font-family);
      font-weight: 700;
      font-size: 1rem;
      border-radius: 12px;
      cursor: pointer;
      box-shadow: 0 4px 15px rgba(255, 0, 122, 0.35);
      transition: all 0.3s ease;
      margin-top: 10px;
    }
    .btn:hover {
      background-position: right center;
      transform: translateY(-2px);
      box-shadow: 0 6px 22px rgba(255, 0, 122, 0.5), 0 0 10px rgba(0, 240, 255, 0.3);
    }
    .alert {
      background: rgba(239, 68, 68, 0.15);
      border: 1px solid rgba(239, 68, 68, 0.3);
      color: #FCA5A5;
      padding: 12px;
      border-radius: 10px;
      font-size: 0.85rem;
      margin-bottom: 20px;
      display: none;
    }
    .seed-info {
      font-size: 0.75rem;
      color: var(--text-secondary);
      margin-top: 24px;
      border-top: 1px solid rgba(255,255,255,0.05);
      padding-top: 16px;
    }
    code {
      background: rgba(0,0,0,0.3);
      padding: 2px 6px;
      border-radius: 4px;
      color: #FBBF24;
    }
  </style>

</head>
<body>
  <div class="bg-blobs">
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
  </div>

  <div class="login-card">
    <div class="logo-container">
      <h1>CrossPublish</h1>
      <p>Cross-Posting Automation Workspace</p>
    </div>

    <div class="alert" id="error-alert"></div>

    <form id="login-form">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" required placeholder="Enter username">
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" required placeholder="••••••••">
      </div>
      <button type="submit" class="btn">Sign In to Dashboard</button>
    </form>

    <div class="seed-info">
      Default Admin Login:<br>
      User: <code>superadmin</code> / Pass: <code>superpassword</code>
    </div>
  </div>

  <script>
    const loginForm = document.getElementById('login-form');
    const errorAlert = document.getElementById('error-alert');

    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      errorAlert.style.display = 'none';

      const username = document.getElementById('username').value;
      const password = document.getElementById('password').value;
      const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

      try {
        const res = await fetch('/api/auth/login', {
          method: 'POST',
          headers: { 
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
          },
          body: JSON.stringify({ username, password })
        });
        const data = await res.json();
        
        if (res.ok && data.success) {
          // Redirect to main dashboard
          window.location.href = '/';
        } else {
          errorAlert.textContent = data.error || 'Authentication failed.';
          errorAlert.style.display = 'block';
        }
      } catch (err) {
        errorAlert.textContent = 'Server connection error.';
        errorAlert.style.display = 'block';
      }
    });
  </script>
</body>
</html>
