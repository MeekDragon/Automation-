<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>OmniPublish Dashboard</title>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- Stylesheet -->
  <link rel="stylesheet" href="style.css">
  <style>
    /* Styling extension for new features */
    .header-top {
      display: flex;
      justify-content: space-between;
      align-items: center;
      width: 100%;
    }
    .user-profile-actions {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .advanced-settings-accordion {
      background: rgba(13, 18, 30, 0.4);
      border: 1px solid var(--input-border);
      border-radius: 12px;
      padding: 12px 16px;
      margin-bottom: 22px;
    }
    .advanced-settings-accordion summary {
      cursor: pointer;
      font-weight: 600;
      font-size: 0.9rem;
      user-select: none;
    }
    .advanced-fields select {
      width: 100%;
      background: var(--input-bg);
      border: 1px solid var(--input-border);
      border-radius: 10px;
      padding: 10px 12px;
      color: var(--text-primary);
      font-family: var(--font-family);
    }
    .share-btn-group {
      display: flex;
      gap: 8px;
      margin-top: 4px;
    }
    .btn-share {
      background: rgba(255,255,255,0.06);
      border: 1px solid rgba(255,255,255,0.1);
      padding: 4px 10px;
      font-size: 0.75rem;
      border-radius: 6px;
      cursor: pointer;
      color: var(--text-primary);
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      transition: all 0.2s;
    }
    .btn-share:hover {
      background: var(--accent-primary);
      border-color: var(--accent-primary);
    }
    .btn-share.wa {
      background: rgba(16, 185, 129, 0.1);
      border-color: rgba(16, 185, 129, 0.2);
      color: #A7F3D0;
    }
    .btn-share.wa:hover {
      background: #10B981;
      color: white;
    }
  </style>
</head>
<body>
  <div class="glass-bg-blobs">
    <div class="blob blob-purple"></div>
    <div class="blob blob-blue"></div>
  </div>

  <div class="app-container">
    <!-- Header -->
    <header class="app-header">
      <div class="header-logo">
        <h1>OmniPublish</h1>
      </div>
      <nav class="header-nav">
        <a href="#" class="nav-link active" data-tab="dashboard">Dashboard</a>
        <a href="#" class="nav-link" data-tab="campaigns">Campaigns</a>
        <a href="#" class="nav-link" data-tab="analytics">Analytics</a>
        <a href="#" class="nav-link" data-tab="settings">Settings</a>
      </nav>
      <div class="header-right">
        <div class="search-bar-mock">
          <span>🔍</span> Search campaigns...
        </div>
        <div class="bell-icon-container">
          <div class="bell-icon" id="bell-icon">
            🔔
            <span class="bell-badge hidden" id="bell-badge"></span>
          </div>
          <div class="notifications-dropdown hidden" id="notifications-dropdown">
            <div class="notif-dropdown-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255, 255, 255, 0.08); padding-bottom: 10px; margin-bottom: 10px;">
              <h3 style="margin: 0; font-size: 0.95rem; font-weight: 700; color: white;">Notifications</h3>
              <button id="btn-clear-notifications" class="btn btn-secondary btn-sm" style="padding: 2px 8px; font-size: 0.72rem; border-radius: 4px;">Clear All</button>
            </div>
            <div id="notifications-list" style="max-height: 250px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px;">
              <p class="empty-state" style="padding: 16px 0; text-align: center; color: var(--text-muted); font-size: 0.8rem;">No new notifications</p>
            </div>
          </div>
        </div>
        <div class="user-avatar-container">
          <div class="avatar-circle" id="user-avatar-initials">S</div>
          <span id="user-display-name" style="font-weight: 600; color: var(--text-secondary);"></span>
          <button id="btn-logout" class="btn btn-secondary btn-sm" style="margin-left: 8px;">🚪 Logout</button>
        </div>
      </div>
    </header>

    <!-- Dashboard Tab View -->
    <div id="tab-dashboard" class="tab-content">
      <!-- SaaS Telemetry Stats Row -->
      <section class="stats-row">
      <div class="stat-card published">
        <div class="stat-icon">
          <svg class="stat-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
        </div>
        <div class="stat-info">
          <span class="stat-value" id="stats-published">0</span>
          <span class="stat-label">Posts Published</span>
        </div>
      </div>
      <div class="stat-card connected">
        <div class="stat-icon">
          <svg class="stat-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
        </div>
        <div class="stat-info">
          <span class="stat-value" id="stats-connected">0</span>
          <span class="stat-label">Connected Channels</span>
        </div>
      </div>
      <div class="stat-card scheduled">
        <div class="stat-icon">
          <svg class="stat-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
        </div>
        <div class="stat-info">
          <span class="stat-value" id="stats-scheduled">0</span>
          <span class="stat-label">Scheduled Posts</span>
        </div>
      </div>
      <div class="stat-card failed">
        <div class="stat-icon">
          <svg class="stat-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
        </div>
        <div class="stat-info">
          <span class="stat-value" id="stats-failed">0</span>
          <span class="stat-label">Failed Posts</span>
        </div>
      </div>
    </section>

    <!-- Main Grid Layout -->
    <main class="dashboard-grid">
      
      <!-- Left Column: Settings and Channel Links (Read-only status overview) -->
      <section class="left-column">
        <div class="card glass-card">
          <h2>Linked Channels</h2>
          <p class="card-desc">Authenticate your platforms for one-click publishing.</p>
          
          <div class="channels-list">
            <!-- YouTube Account Card -->
            <div class="channel-item" id="channel-youtube">
              <div class="channel-info">
                <span class="channel-icon yt">
                  <svg class="brand-svg-icon" viewBox="0 0 24 24"><path d="M23.498 6.163a3.003 3.003 0 0 0-2.11-2.11C19.517 3.545 12 3.545 12 3.545s-7.517 0-9.388.508a3.003 3.003 0 0 0-2.11 2.11C0 8.033 0 12 0 12s0 3.967.502 5.837a3.003 3.003 0 0 0 2.11 2.11c1.871.508 9.388.508 9.388.508s7.517 0 9.388-.508a3.003 3.003 0 0 0 2.11-2.11C24 15.967 24 12 24 12s0-3.967-.502-5.837zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                </span>
                <div>
                  <h3>YouTube</h3>
                  <p class="channel-status" id="yt-status"><span class="dot-indicator disconnected">●</span> Not Connected</p>
                </div>
              </div>
            </div>

            <!-- Instagram Account Card -->
            <div class="channel-item" id="channel-instagram" style="margin-top: 14px;">
              <div class="channel-info">
                <span class="channel-icon ig">
                  <svg class="brand-svg-icon stroke-svg" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
                </span>
                <div>
                  <h3>Instagram</h3>
                  <p class="channel-status" id="ig-status"><span class="dot-indicator disconnected">●</span> Not Connected</p>
                </div>
              </div>
            </div>

            <!-- WordPress CMS Account Card -->
            <div class="channel-item" id="channel-wordpress" style="margin-top: 14px;">
              <div class="channel-info">
                <span class="channel-icon wp">
                  <svg class="brand-svg-icon" viewBox="0 0 24 24"><path d="M12.158 12.786l-2.698 7.84c.806.236 1.657.365 2.54.365a9.55 9.55 0 0 0 3.733-.757m-3.575-7.448h.001zm8.016 4.708a9.49 9.49 0 0 0 .563-3.21c0-1.89-.645-3.208-1.2-4.173-.55-.965-1.062-1.799-1.062-2.776 0 1.09.435 1.933.955 2.87.522.937 1.11 1.983 1.11 3.84a8.673 8.673 0 0 1-.366 2.449zm-8.877-10.74l3.18 8.685 1.666-5.023c.427-1.267.755-1.996.755-2.733a3.523 3.523 0 0 0-.256-1.332A9.53 9.53 0 0 0 12 2.408c-.7 0-1.378.077-2.03.22zm-7.669 8.24c0-2.316.92-4.632 2.49-6.31l3.524 9.684-3.486-9.61A9.515 9.515 0 0 0 2.408 12c0 2.449.921 4.688 2.43 6.402zm6.657 7.21l-3.324-9.664H8.71l3.23 9.395-.873 2.54zm.002 0c-.001-.002-1.82-5.289-1.82-5.289l-1.41 4.1h.001c.954.767 2.134 1.189 3.229 1.189zm0 0c.957 0 1.865-.246 2.668-.677l-.842-2.45-1.826 3.127zm.001 0a9.54 9.54 0 0 0 5.176-1.54L13.167 12.03zm-1.823 0h.002a9.538 9.538 0 0 0 4.22-3.12l-1.397-4.062z"/></svg>
                </span>
                <div>
                  <h3>WordPress CMS</h3>
                  <p class="channel-status" id="wp-status"><span class="dot-indicator disconnected">●</span> Not Connected</p>
                </div>
              </div>
            </div>
          </div>
          <div style="margin-top: 14px; padding-top: 10px; border-top: 1px solid var(--card-border); font-size: 0.75rem; color: var(--text-muted); text-align: center;">
            💡 Visit the <strong>Settings</strong> tab to link or unlink channels.
          </div>
        </div>

        <!-- Presets Configuration Card (Read-only) -->
        <div class="card glass-card" style="margin-top: 24px;">
          <h2>Default Presets</h2>
          <p class="card-desc">Prefill composer parameters and tags automatically.</p>
          
          <div style="margin-top: 16px; display: flex; flex-direction: column; gap: 12px;">
            <div>
              <span style="font-size: 0.76rem; color: var(--text-muted); display: block; margin-bottom: 2px;">Default Tags</span>
              <strong id="dashboard-preset-tags" style="font-size: 0.88rem; color: var(--text-primary);">None</strong>
            </div>
            <div>
              <span style="font-size: 0.76rem; color: var(--text-muted); display: block; margin-bottom: 2px;">Default Description Footer</span>
              <p id="dashboard-preset-desc" style="font-size: 0.88rem; color: var(--text-primary); white-space: pre-wrap; line-height: 1.4;">None</p>
            </div>
            <div style="margin-top: 6px; padding-top: 12px; border-top: 1px solid var(--card-border); font-size: 0.75rem; color: var(--text-muted); text-align: center;">
              💡 Visit the <strong>Settings</strong> tab to edit these values.
            </div>
          </div>
        </div>

        <!-- System Alert Messages -->
        <div id="status-alert" class="alert hidden">
          <p id="alert-text"></p>
        </div>
      </section>

      <!-- Right Column: Media Composer & Publish Pane -->
      <section class="right-column">
        <form id="composer-form" class="card glass-card form-card">
          <h2>Create Content</h2>
          <p class="card-desc" style="margin-bottom: 20px;">Draft post updates, upload media, and choose destination channels.</p>

          <!-- Step Indicators -->
          <div class="step-indicators">
            <div class="step-indicator-line-progress" id="step-progress-line"></div>
            <div class="step-indicator active" data-step="1" id="ind-step-1">
              <div class="step-number">1</div>
              <div class="step-title">Media</div>
            </div>
            <div class="step-indicator" data-step="2" id="ind-step-2">
              <div class="step-number">2</div>
              <div class="step-title">Details</div>
            </div>
            <div class="step-indicator" data-step="3" id="ind-step-3">
              <div class="step-number">3</div>
              <div class="step-title">Targets</div>
            </div>
          </div>

          <!-- Step 1: Media Upload -->
          <div class="form-step active" id="step-1">
            <div class="form-group">
              <label>Upload Media File</label>
              <div class="dropzone" id="dropzone">
                <input type="file" id="media-input" name="media" accept="image/*,video/*">
                <div class="dropzone-text" id="dropzone-text">
                  <svg class="upload-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                  <p style="font-weight:600; margin-bottom: 4px; font-size: 0.95rem; color: var(--text-primary);">Upload Media</p>
                  <p style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 8px;">Drag & drop images/videos here or browse files</p>
                  <div class="format-chips">
                    <span class="chip">PNG</span>
                    <span class="chip">JPG</span>
                    <span class="chip">MP4</span>
                    <span class="chip">MOV</span>
                  </div>
                </div>
                <div class="preview-container hidden" id="preview-container">
                  <img id="img-preview" class="media-preview hidden" alt="Upload Preview">
                  <video id="video-preview" class="media-preview hidden" controls></video>
                  <button type="button" class="btn-remove-preview" id="btn-remove-preview">✕ Remove Media</button>
                </div>
              </div>
            </div>
            <div class="step-nav-buttons" style="justify-content: flex-end;">
              <button type="button" class="btn btn-primary" id="btn-next-1">Next Step ➜</button>
            </div>
          </div>

          <!-- Step 2: Content Details -->
          <div class="form-step" id="step-2">
            <div class="form-group">
              <label for="title-input">Post Title / Video Title</label>
              <input type="text" id="title-input" name="title" placeholder="Enter post or video title">
            </div>
            <div class="form-group">
              <label for="desc-input">Description / Caption</label>
              <textarea id="desc-input" name="description" rows="4" placeholder="Write description or Instagram caption here..."></textarea>
            </div>
            <div class="form-group">
              <label for="tags-input">Tags / Hashtags (Comma-separated)</label>
              <input type="text" id="tags-input" name="tags" placeholder="e.g. tech, business, coding, automation">
            </div>
            <div class="step-nav-buttons">
              <button type="button" class="btn btn-secondary" id="btn-prev-2">⬅ Back</button>
              <button type="button" class="btn btn-primary" id="btn-next-2">Next Step ➜</button>
            </div>
          </div>

          <!-- Step 3: Select Platforms & Schedule -->
          <div class="form-step" id="step-3">
            <!-- Platform Targets -->
            <div class="form-group">
              <label>Publish Destinations</label>
              <div class="destination-selectors">
                <!-- YT Video -->
                <label class="selector-checkbox" id="selector-yt-video-label">
                  <input type="checkbox" name="platforms" value="youtube_video">
                  <div class="selector-box">
                    <span class="selector-indicator"></span>
                    <span class="sel-icon">📺</span>
                    <div>
                      <strong>YouTube Video</strong>
                      <span class="sel-sub">Horizontal Video</span>
                    </div>
                  </div>
                </label>
                <!-- YT Shorts -->
                <label class="selector-checkbox" id="selector-yt-shorts-label">
                  <input type="checkbox" name="platforms" value="youtube_shorts">
                  <div class="selector-box">
                    <span class="selector-indicator"></span>
                    <span class="sel-icon">⚡</span>
                    <div>
                      <strong>YouTube Shorts</strong>
                      <span class="sel-sub">Vertical Video (&lt;60s)</span>
                    </div>
                  </div>
                </label>
                <!-- YT Community Post -->
                <label class="selector-checkbox" id="selector-yt-post-label">
                  <input type="checkbox" name="platforms" value="youtube_post">
                  <div class="selector-box">
                    <span class="selector-indicator"></span>
                    <span class="sel-icon">💬</span>
                    <div>
                      <strong>YouTube Post</strong>
                      <span class="sel-sub">Community Text/Image</span>
                    </div>
                  </div>
                </label>
                <!-- Instagram Post -->
                <label class="selector-checkbox" id="selector-ig-post-label">
                  <input type="checkbox" name="platforms" value="instagram_post">
                  <div class="selector-box">
                    <span class="selector-indicator"></span>
                    <span class="sel-icon">📸</span>
                    <div>
                      <strong>Instagram Post</strong>
                      <span class="sel-sub">Grid Image or Video</span>
                    </div>
                  </div>
                </label>
                <!-- Instagram Reel -->
                <label class="selector-checkbox" id="selector-ig-reel-label">
                  <input type="checkbox" name="platforms" value="instagram_reel">
                  <div class="selector-box">
                    <span class="selector-indicator"></span>
                    <span class="sel-icon">🎥</span>
                    <div>
                      <strong>Instagram Reel</strong>
                      <span class="sel-sub">Vertical Video</span>
                    </div>
                  </div>
                </label>
                <!-- Instagram Story -->
                <label class="selector-checkbox" id="selector-ig-story-label">
                  <input type="checkbox" name="platforms" value="instagram_story">
                  <div class="selector-box">
                    <span class="selector-indicator"></span>
                    <span class="sel-icon">✨</span>
                    <div>
                      <strong>Instagram Story</strong>
                      <span class="sel-sub">24h Vertical Media</span>
                    </div>
                  </div>
                </label>
                <!-- WordPress -->
                <label class="selector-checkbox" id="selector-wp-label">
                  <input type="checkbox" name="platforms" value="wordpress">
                  <div class="selector-box">
                    <span class="selector-indicator"></span>
                    <span class="sel-icon">📰</span>
                    <div>
                      <strong>WordPress CMS</strong>
                      <span class="sel-sub">Blog Post / Article</span>
                    </div>
                  </div>
                </label>
              </div>
            </div>

            <!-- Platform-Specific Options (Accordion) -->
            <details class="advanced-settings-accordion">
              <summary>⚙️ Advanced Platform Options</summary>
              <div class="advanced-fields" style="margin-top: 14px; display: flex; flex-direction: column; gap: 14px;">
                <div class="form-group">
                  <label for="yt-privacy">YouTube Privacy Status</label>
                  <select id="yt-privacy" name="youtubePrivacy">
                    <option value="public" selected>Public (Immediate)</option>
                    <option value="unlisted">Unlisted (Hidden)</option>
                    <option value="private">Private (Only You)</option>
                  </select>
                </div>
                <div class="form-group">
                  <label for="yt-category">YouTube Video Category</label>
                  <select id="yt-category" name="youtubeCategory">
                    <option value="22" selected>People & Blogs</option>
                    <option value="23">Comedy</option>
                    <option value="24">Entertainment</option>
                    <option value="27">Education</option>
                    <option value="28">Science & Technology</option>
                  </select>
                </div>
                <div class="form-group">
                  <label for="wp-status-select">WordPress Post Status</label>
                  <select id="wp-status-select" name="wordpressStatus">
                    <option value="publish" selected>Publish Immediately</option>
                    <option value="draft">Save as Draft</option>
                  </select>
                </div>
              </div>
            </details>

            <!-- Post Scheduling -->
            <div class="form-group" style="background: rgba(255,255,255,0.02); padding: 16px; border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; margin-bottom: 22px;">
              <label for="schedule-input">⏰ Schedule for Later (Optional)</label>
              <input type="datetime-local" id="schedule-input" name="scheduledAt" style="margin-top: 6px;">
              <span class="form-sub-text">Leave empty to publish instantly.</span>
            </div>

            <!-- Step 3 Action Navigation -->
            <div class="step-nav-buttons">
              <button type="button" class="btn btn-secondary" id="btn-prev-3">⬅ Back</button>
              <button type="submit" class="btn btn-primary" id="btn-publish" style="padding: 12px 24px;">
                <span class="btn-text">🚀 Publish to Channels</span>
                <div class="spinner hidden" id="publish-spinner"></div>
              </button>
            </div>
          </div>
        </form>

        <!-- Active Pipeline & Publishing Logs -->
        <section class="publishing-monitor" style="margin-top: 32px;">
          <div class="card glass-card">
            <div class="monitor-header">
              <h2>Active Pipeline Monitor</h2>
              <button id="btn-refresh-jobs" class="btn btn-secondary btn-sm">🔄 Refresh List</button>
            </div>
            <p class="card-desc">Monitor uploads progress, conversion transcoding, and API statuses in real time.</p>
            
            <div class="monitor-content">
              <!-- Job History List -->
              <div class="jobs-list-container">
                <h3>Recent Publish History</h3>
                <div class="jobs-list" id="jobs-list">
                  <p class="empty-state">No jobs processed yet.</p>
                </div>
              </div>

              <!-- Active Logs Pane & Sharing panel -->
              <div class="logs-pane-container">
                <h3>Recent Activity</h3>
                <div class="activity-timeline" id="activity-timeline">
                  <p class="empty-state">Select a history item or submit a post to view activity.</p>
                </div>
                
                <!-- Show raw logs console toggle -->
                <div style="margin-top: 6px; display: flex; align-items: center;">
                  <label style="font-size: 0.78rem; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; gap: 8px; user-select: none;">
                    <input type="checkbox" id="toggle-raw-logs" style="width: auto; cursor: pointer;"> Show technical console logs
                  </label>
                </div>

                <div class="logs-pane hidden" id="logs-pane" style="margin-top: 10px;">
                  <p class="empty-state">Console logs hidden. Check the toggle to display raw stream.</p>
                </div>

                <!-- Share Buttons Panel -->
                <div class="share-panel hidden" id="share-panel" style="margin-top: 16px; padding: 16px; background: rgba(245,158,11,0.06); border: 1px dashed rgba(245,158,11,0.25); border-radius: 16px;">
                  <h3 style="font-size: 0.9rem; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">🔗 Links & Share Shortcuts</h3>
                  <div id="share-links-container" style="display: flex; flex-direction: column; gap: 10px;">
                    <!-- Dynamically populated links -->
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </section>
    </main>
    </div> <!-- End tab-dashboard -->

    <!-- Campaigns Tab View -->
    <div id="tab-campaigns" class="tab-content hidden">
      <section class="campaigns-section" style="margin-top: 32px;">
        <div class="card glass-card">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div>
              <h2>Campaigns & Jobs Registry</h2>
              <p class="card-desc">Comprehensive log of all scheduled, active, and completed publishing pipelines.</p>
            </div>
            <div style="display: flex; gap: 12px; align-items: center;">
              <input type="text" id="campaigns-search" placeholder="🔍 Filter by title..." style="padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; max-width: 240px; background: rgba(255,255,255,0.02); border: 1px solid var(--card-border); color: white;">
              <button id="btn-refresh-campaigns" class="btn btn-secondary btn-sm">🔄 Refresh</button>
            </div>
          </div>
          <div style="overflow-x: auto;">
            <table class="campaigns-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.85rem; min-width: 600px;">
              <thead>
                <tr style="border-bottom: 1px solid var(--card-border); color: var(--text-secondary);">
                  <th style="padding: 12px 8px;">Job ID</th>
                  <th style="padding: 12px 8px;">Title</th>
                  <th style="padding: 12px 8px;">Destinations</th>
                  <th style="padding: 12px 8px;">Scheduled For</th>
                  <th style="padding: 12px 8px;">Created By</th>
                  <th style="padding: 12px 8px;">Created At</th>
                  <th style="padding: 12px 8px;">Status</th>
                </tr>
              </thead>
              <tbody id="campaigns-table-body">
                <tr>
                  <td colspan="7" style="text-align: center; padding: 24px; color: var(--text-muted);">No campaigns found.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </div>

    <!-- Analytics Tab View -->
    <div id="tab-analytics" class="tab-content hidden">
      <section class="analytics-section" style="margin-top: 32px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;">
          <!-- Card 1: Channel Breakdown -->
          <div class="card glass-card">
            <h2>Channel Breakdown</h2>
            <p class="card-desc">Cross-platform publish distribution percentage.</p>
            <div style="margin-top: 24px; display: flex; flex-direction: column; gap: 16px;">
              <div>
                <div style="display: flex; justify-content: space-between; font-size: 0.8rem; margin-bottom: 6px;">
                  <span>YouTube / Shorts</span>
                  <span id="analytics-yt-pct">0%</span>
                </div>
                <div style="width: 100%; height: 8px; background: rgba(255,255,255,0.05); border-radius: 4px; overflow: hidden;">
                  <div id="analytics-yt-bar" style="width: 0%; height: 100%; background: var(--yt-red); border-radius: 4px; transition: width 0.6s ease;"></div>
                </div>
              </div>
              <div>
                <div style="display: flex; justify-content: space-between; font-size: 0.8rem; margin-bottom: 6px;">
                  <span>Instagram Posts & Reels</span>
                  <span id="analytics-ig-pct">0%</span>
                </div>
                <div style="width: 100%; height: 8px; background: rgba(255,255,255,0.05); border-radius: 4px; overflow: hidden;">
                  <div id="analytics-ig-bar" style="width: 0%; height: 100%; background: var(--ig-pink); border-radius: 4px; transition: width 0.6s ease;"></div>
                </div>
              </div>
              <div>
                <div style="display: flex; justify-content: space-between; font-size: 0.8rem; margin-bottom: 6px;">
                  <span>WordPress CMS</span>
                  <span id="analytics-wp-pct">0%</span>
                </div>
                <div style="width: 100%; height: 8px; background: rgba(255,255,255,0.05); border-radius: 4px; overflow: hidden;">
                  <div id="analytics-wp-bar" style="width: 0%; height: 100%; background: var(--wp-blue); border-radius: 4px; transition: width 0.6s ease;"></div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Card 2: Success Rates -->
          <div class="card glass-card">
            <h2>Pipeline Quality Assurance</h2>
            <p class="card-desc">Telemetry delivery success ratios.</p>
            <div style="margin-top: 16px; display: flex; align-items: center; justify-content: center; height: 160px; position: relative;">
              <div style="text-align: center;">
                <span id="analytics-success-ratio" style="font-size: 2.8rem; font-weight: 800; background: linear-gradient(135deg, #10B981 0%, #34D399 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">0%</span>
                <p style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 4px;">Success Ratio</p>
              </div>
            </div>
            <div style="display: flex; justify-content: space-around; font-size: 0.8rem; border-top: 1px solid var(--card-border); padding-top: 14px;">
              <div style="text-align: center;">
                <span id="analytics-completed-count" style="font-weight: 700; color: var(--accent-success);">0</span>
                <p style="color: var(--text-muted); font-size: 0.75rem;">Succeeded</p>
              </div>
              <div style="text-align: center;">
                <span id="analytics-failed-count" style="font-weight: 700; color: var(--accent-danger);">0</span>
                <p style="color: var(--text-muted); font-size: 0.75rem;">Failed</p>
              </div>
              <div style="text-align: center;">
                <span id="analytics-pending-count" style="font-weight: 700; color: var(--accent-warning);">0</span>
                <p style="color: var(--text-muted); font-size: 0.75rem;">In Progress</p>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>

    <!-- Settings Tab View -->
    <div id="tab-settings" class="tab-content hidden">
      <section class="settings-section" style="margin-top: 32px; display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 24px;">
        <div class="card glass-card">
          <h2>Default Presets</h2>
          <p class="card-desc">Prefill composer parameters and tags automatically.</p>
          <form id="settings-presets-form-tab">
            <div class="form-group" style="margin-top: 16px;">
              <label for="default-tags-tab">Default Tags (Comma-separated)</label>
              <input type="text" id="default-tags-tab" placeholder="e.g. coding, automation">
            </div>
            <div class="form-group">
              <label for="default-desc-tab">Default Description Footer</label>
              <textarea id="default-desc-tab" rows="4" placeholder="e.g. Follow for more automation tips!"></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-block" style="padding: 12px; font-size: 0.9rem;">💾 Save Default Settings</button>
          </form>
        </div>
        
        <div class="card glass-card">
          <h2>Linked Channels</h2>
          <p class="card-desc">Authenticate your platforms for one-click publishing.</p>
          <div style="margin-top: 16px; display: flex; flex-direction: column; gap: 16px;" id="settings-channels-list">
            <!-- Dynamically populated -->
          </div>
        </div>
      </section>
    </div>
  </div>

  <!-- WordPress Credentials Modal -->
  <div class="modal-backdrop hidden" id="wp-modal">
    <div class="modal glass-card">
      <div class="modal-header">
        <h2>Link WordPress Website</h2>
        <button class="modal-close" id="wp-modal-close">✕</button>
      </div>
      <form id="wp-link-form">
        <div class="form-group">
          <label for="wp-url-input">Website URL</label>
          <input type="url" id="wp-url-input" placeholder="e.g. https://mybusinessblog.com" required>
        </div>
        <div class="form-group">
          <label for="wp-user-input">Admin Username</label>
          <input type="text" id="wp-user-input" placeholder="e.g. admin_wp" required>
        </div>
        <div class="form-group">
          <label for="wp-pw-input">WordPress Application Password</label>
          <input type="password" id="wp-pw-input" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx" required>
          <span class="form-sub-text">Generate this in WordPress dashboard: <strong>Users → Profile → Application Passwords</strong>.</span>
        </div>
        <button type="submit" class="btn btn-primary btn-block" id="btn-wp-save">
          <span class="btn-text">💾 Save Site Link</span>
          <div class="spinner hidden" id="wp-spinner"></div>
        </button>
      </form>
    </div>
  </div>

  <script src="app.js"></script>
</body>
</html>
