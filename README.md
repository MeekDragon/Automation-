# OmniPublish: One-Click Cross-Posting Automation Tool

OmniPublish is a lightweight, local automation tool that allows you to publish videos, images, and blog updates across YouTube, Instagram, and WordPress simultaneously from a unified, elegant desktop dashboard.

---

## Features
*   **One-Time Authentication:** Links your accounts securely using standard OAuth 2.0 (YouTube), long-lived Meta tokens (Instagram), or Application Passwords (WordPress).
*   **Secure Local Storage:** Credentials are saved in a local JSON database, encrypted with **AES-256-GCM** using a key stored in your private `.env` file. No cloud servers store your keys!
*   **Automated Formatting:** Resizes images to Instagram specs, and checks/converts landscape videos to vertical Reels/Shorts with blurry background padding if FFmpeg is installed.
*   **Zero-Config Public Bypass:** Resolves local file retrieval restrictions by uploading media to a secure, temporary anonymous sharing engine (`tmpfiles.org`) when communicating with Meta's servers.

---

## 🛠️ Quick Start

### 1. Install Dependencies
You must have [Node.js](https://nodejs.org/) (v16 or higher) installed on your system.

Navigate to the project folder and run:
```bash
npm install
```

### 2. Auto-Configure Environment & Self-Test
Run the self-test script. This will automatically copy `.env.example` to `.env` and generate a secure **random encryption key** for you:
```bash
node test-connections.js
```

### 3. Run the App
Start the local server:
```bash
npm start
```
Open your browser and visit: **[http://localhost:3000](http://localhost:3000)**

---

## 🔑 Platform Setup Guide

Since you are running this locally for personal use, you need to register developer applications to get your client secrets. Here is a step-by-step guide:

### A. YouTube API Integration (Google Cloud Console)
1. Go to the [Google Cloud Console](https://console.cloud.google.com/).
2. Create a new project named `OmniPublish`.
3. In the sidebar, navigate to **APIs & Services → Library**. Search for **YouTube Data API v3** and click **Enable**.
4. Navigate to **OAuth Consent Screen**:
   * Choose **External** user type.
   * Fill out the app name and your email address.
   * Under **Scopes**, add `/auth/youtube.upload` and `/auth/youtube.readonly`.
   * Under **Test Users**, add your own Gmail account (this is critical in sandbox mode).
5. Navigate to **Credentials**:
   * Click **Create Credentials** → **OAuth Client ID**.
   * Choose application type **Web Application**.
   * Under **Authorized Redirect URIs**, enter exactly:
     `http://localhost:3000/auth/youtube/callback`
   * Click **Create** and copy the Client ID and Client Secret into your `.env` file.

---

### B. Instagram / Meta Graph API Integration
1. Go to the [Meta for Developers](https://developers.facebook.com/) portal.
2. Click **Create App** → Choose **Other** → Select **Business** app type.
3. In your App Dashboard, click **Set Up** on **Facebook Login for Business**.
4. In the Facebook Login settings sidebar:
   * Under **Valid OAuth Redirect URIs**, enter exactly:
     `http://localhost:3000/auth/instagram/callback`
5. Go to **Settings → Basic** in the sidebar to get your **App ID** and **App Secret**. Copy these into your `.env` file.
6. **Requirements Check:**
   * Your Instagram Account must be a **Business** or **Creator** account (convert it in the Instagram mobile app under Settings).
   * Your Instagram account must be linked to a **Facebook Page** that you manage.
   * When you click "Link" in OmniPublish, log in to Facebook, select that specific Page, and grant all requested permissions.

---

### C. WordPress Integration
1. Log in to your WordPress Admin Dashboard (`/wp-admin`).
2. Go to **Users → Profile**.
3. Scroll down to the **Application Passwords** section.
4. Type an app name (e.g., `OmniPublish`) and click **Add New Application Password**.
5. Copy the generated 24-character password.
6. In the OmniPublish UI dashboard, click **Link WordPress**, fill in your website URL, admin username, and paste the application password.

---

## 📹 Installing FFmpeg (Optional)

If FFmpeg is installed, OmniPublish will automatically format landscape videos into vertical 9:16 files with blurred background pads when uploading to Instagram Reels or YouTube Shorts.

*   **macOS (via Homebrew):**
    ```bash
    brew install ffmpeg
    ```
*   **Windows (via Scoop/Choco):**
    ```powershell
    scoop install ffmpeg
    ```
    *(Or download the binary from the official site and add it to your system PATH environment variable).*

---

## 🔒 Security Architecture Details
OmniPublish utilizes **AES-256-GCM** encryption. When you authenticate through Google or Facebook, the access tokens are encrypted using the unique `TOKEN_ENCRYPTION_KEY` from your `.env` file. The output, along with the cryptographically secure initialization vector (IV) and authentication tag, is saved in `db.json`. 

Even if someone accesses your `db.json` file, they cannot decrypt your credentials without your secret `.env` key.
