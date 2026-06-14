// OmniPublish Dashboard Client Logic

// State
let currentUser = null;
let linkedAccounts = {};
let activeJobId = null;
let pollInterval = null;
let defaultSettings = {};
let currentJobsList = [];

// DOM Elements
const userDisplayName = document.getElementById('user-display-name');
const btnLogout = document.getElementById('btn-logout');

const ytStatus = document.getElementById('yt-status');
const ytLinkBtn = document.getElementById('yt-link-btn');
const ytUnlinkBtn = document.getElementById('yt-unlink-btn');

const igStatus = document.getElementById('ig-status');
const igLinkBtn = document.getElementById('ig-link-btn');
const igUnlinkBtn = document.getElementById('ig-unlink-btn');

const wpStatus = document.getElementById('wp-status');
const wpLinkBtn = document.getElementById('wp-link-btn');
const wpUnlinkBtn = document.getElementById('wp-unlink-btn');

// Form Selectors & Presets
const selectorYtVideo = document.querySelector('input[value="youtube_video"]');
const selectorYtShorts = document.querySelector('input[value="youtube_shorts"]');
const selectorYtPost = document.querySelector('input[value="youtube_post"]');
const selectorIgPost = document.querySelector('input[value="instagram_post"]');
const selectorIgReel = document.querySelector('input[value="instagram_reel"]');
const selectorIgStory = document.querySelector('input[value="instagram_story"]');
const selectorWp = document.querySelector('input[value="wordpress"]');

const labelYtVideo = document.getElementById('selector-yt-video-label');
const labelYtShorts = document.getElementById('selector-yt-shorts-label');
const labelYtPost = document.getElementById('selector-yt-post-label');
const labelIgPost = document.getElementById('selector-ig-post-label');
const labelIgReel = document.getElementById('selector-ig-reel-label');
const labelIgStory = document.getElementById('selector-ig-story-label');
const labelWp = document.getElementById('selector-wp-label');

const presetsForm = document.getElementById('settings-presets-form-tab');
const defaultTagsInput = document.getElementById('default-tags-tab');
const defaultDescTextarea = document.getElementById('default-desc-tab');

// Composer Form
const composerForm = document.getElementById('composer-form');
const mediaInput = document.getElementById('media-input');
const dropzone = document.getElementById('dropzone');
const dropzoneText = document.getElementById('dropzone-text');
const previewContainer = document.getElementById('preview-container');
const imgPreview = document.getElementById('img-preview');
const videoPreview = document.getElementById('video-preview');
const btnRemovePreview = document.getElementById('btn-remove-preview');
const btnPublish = document.getElementById('btn-publish');
const publishSpinner = document.getElementById('publish-spinner');
const scheduleInput = document.getElementById('schedule-input');

// Multi-step Wizard Buttons
const btnNext1 = document.getElementById('btn-next-1');
const btnNext2 = document.getElementById('btn-next-2');
const btnPrev2 = document.getElementById('btn-prev-2');
const btnPrev3 = document.getElementById('btn-prev-3');

// Lists, Logs, Shares
const jobsList = document.getElementById('jobs-list');
const logsPane = document.getElementById('logs-pane');
const activityTimeline = document.getElementById('activity-timeline');
const toggleRawLogs = document.getElementById('toggle-raw-logs');
const btnRefreshJobs = document.getElementById('btn-refresh-jobs');
const sharePanel = document.getElementById('share-panel');
const shareLinksContainer = document.getElementById('share-links-container');

// Alerts
const statusAlert = document.getElementById('status-alert');
const alertText = document.getElementById('alert-text');

// Notifications
let notifications = [];
const bellIcon = document.getElementById('bell-icon');
const bellBadge = document.getElementById('bell-badge');
const notificationsDropdown = document.getElementById('notifications-dropdown');
const notificationsList = document.getElementById('notifications-list');
const btnClearNotifications = document.getElementById('btn-clear-notifications');

// WordPress Modal
const wpModal = document.getElementById('wp-modal');
const wpLinkFormData = document.getElementById('wp-link-form');
const wpModalClose = document.getElementById('wp-modal-close');
const wpUrlInput = document.getElementById('wp-url-input');
const wpUserInput = document.getElementById('wp-user-input');
const wpPwInput = document.getElementById('wp-pw-input');
const btnWpSave = document.getElementById('btn-wp-save');
const wpSpinner = document.getElementById('wp-spinner');

// ----------------------------------------------------
// Initialization & Authentication
// ----------------------------------------------------
document.addEventListener('DOMContentLoaded', async () => {
  const authed = await checkSession();
  if (!authed) return; // Stop if redirecting to login

  checkUrlParams();
  await fetchAccounts();
  await fetchSettings();
  await fetchJobs();
  
  // Start dynamic polling (every 4 seconds)
  pollInterval = setInterval(() => {
    fetchJobs(false);
    if (activeJobId) {
      fetchLogs(activeJobId, false);
      updateSharePanelForActiveJob();
    }
  }, 4000);

  // Setup toggle event listener for raw logs
  if (toggleRawLogs && logsPane) {
    toggleRawLogs.addEventListener('change', () => {
      if (toggleRawLogs.checked) {
        logsPane.classList.remove('hidden');
      } else {
        logsPane.classList.add('hidden');
      }
    });
  }

  // Setup notifications toggle event listeners
  if (bellIcon && notificationsDropdown) {
    bellIcon.addEventListener('click', (e) => {
      e.stopPropagation();
      notificationsDropdown.classList.toggle('hidden');
      if (!notificationsDropdown.classList.contains('hidden')) {
        markAllNotificationsAsRead();
      }
    });
    document.addEventListener('click', (e) => {
      if (notificationsDropdown && !notificationsDropdown.contains(e.target) && e.target !== bellIcon) {
        notificationsDropdown.classList.add('hidden');
      }
    });
  }

  if (btnClearNotifications) {
    btnClearNotifications.addEventListener('click', (e) => {
      e.stopPropagation();
      const readIds = notifications.map(n => n.id);
      localStorage.setItem('read_notification_ids', JSON.stringify(readIds));
      notifications = [];
      renderNotificationsList();
      if (notificationsDropdown) notificationsDropdown.classList.add('hidden');
    });
  }
});

async function checkSession() {
  try {
    const res = await fetch('/api/auth/me');
    if (!res.ok) {
      window.location.href = '/login.html';
      return false;
    }
    const data = await res.json();
    currentUser = data.user;
    userDisplayName.textContent = `${currentUser.username} (${currentUser.role})`;
    
    // Set user avatar initials bubble
    const initialsEl = document.getElementById('user-avatar-initials');
    if (initialsEl && currentUser.username) {
      initialsEl.textContent = currentUser.username.charAt(0).toUpperCase();
    }
    
    // Hide administrative panels for regular Users
    if (currentUser.role === 'user') {
      document.querySelector('.presets-form')?.classList.add('hidden');
      presetsForm.closest('.card').classList.add('hidden');
      document.querySelectorAll('.channel-actions').forEach(el => el.classList.add('hidden'));
    }
    return true;
  } catch (err) {
    window.location.href = '/login.html';
    return false;
  }
}

// Logout handler
btnLogout.addEventListener('click', async () => {
  try {
    const res = await fetch('/api/auth/logout', { method: 'POST' });
    if (res.ok) {
      window.location.href = '/login.html';
    }
  } catch (err) {
    console.error('Logout error:', err);
  }
});

// Helper: Show custom alerts
function showAlert(message, type = 'success') {
  statusAlert.className = `alert alert-${type}`;
  alertText.textContent = message;
  statusAlert.classList.remove('hidden');
  
  setTimeout(() => {
    statusAlert.classList.add('hidden');
  }, 8000);
}

// Friendly Date-Time formatter
function formatDateTime(dateStr) {
  if (!dateStr) return '';
  const date = new Date(dateStr);
  const now = new Date();
  
  // Clear times for day comparison
  const dDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
  const dNow = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  
  const diffTime = dNow.getTime() - dDate.getTime();
  const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));
  
  const timeOptions = { hour: '2-digit', minute: '2-digit', hour12: true };
  const formattedTime = date.toLocaleTimeString([], timeOptions);
  
  if (diffDays === 0) {
    return `Today at ${formattedTime}`;
  } else if (diffDays === 1) {
    return `Yesterday at ${formattedTime}`;
  } else if (diffDays === -1) {
    return `Tomorrow at ${formattedTime}`;
  } else {
    const dateOptions = { month: 'short', day: 'numeric', year: 'numeric' };
    const formattedDate = date.toLocaleDateString([], dateOptions);
    return `${formattedDate}, ${formattedTime}`;
  }
}

// Notifications State Management & Rendering
function updateNotificationsFromJobs(jobs) {
  const readIds = JSON.parse(localStorage.getItem('read_notification_ids') || '[]');
  const newNotifications = [];
  
  // Sort jobs by createdAt ascending so notifications are added in chronological order
  const sortedJobs = [...jobs].sort((a, b) => new Date(a.createdAt) - new Date(b.createdAt));
  
  sortedJobs.forEach(job => {
    let icon = 'ℹ️';
    let title = '';
    let message = '';
    let shouldNotify = false;
    let timestamp = job.createdAt;
    
    if (job.status === 'COMPLETED') {
      icon = '🎉';
      title = 'Publish Successful';
      message = `Post "${job.title}" successfully published to ${Object.keys(job.destinations).map(d => d.toUpperCase()).join(', ')}.`;
      shouldNotify = true;
    } else if (job.status === 'FAILED') {
      icon = '❌';
      title = 'Publish Failed';
      let errStr = 'Invalid Credentials';
      Object.values(job.destinations).forEach(d => { if (d.error) errStr = d.error; });
      message = `Post "${job.title}" failed to publish: ${errStr}.`;
      shouldNotify = true;
    } else if (job.status === 'SCHEDULED') {
      icon = '⏰';
      title = 'Post Scheduled';
      message = `Post "${job.title}" successfully scheduled for ${formatDateTime(job.scheduledAt)}.`;
      shouldNotify = true;
    }
    
    if (shouldNotify) {
      const notifId = `${job.id}_${job.status}`;
      const isUnread = !readIds.includes(notifId);
      newNotifications.unshift({
        id: notifId,
        icon,
        title,
        message,
        timestamp,
        unread: isUnread
      });
    }
  });
  
  notifications = newNotifications;
  renderNotificationsList();
}

function renderNotificationsList() {
  if (!notificationsList) return;
  
  const unreadCount = notifications.filter(n => n.unread).length;
  if (bellBadge) {
    if (unreadCount > 0) {
      bellBadge.textContent = unreadCount;
      bellBadge.classList.remove('hidden');
    } else {
      bellBadge.classList.add('hidden');
    }
  }
  
  if (notifications.length === 0) {
    notificationsList.innerHTML = '<p class="empty-state" style="padding: 16px 0; text-align: center; color: var(--text-muted); font-size: 0.8rem;">No notifications yet</p>';
    return;
  }
  
  let html = '';
  notifications.forEach(n => {
    const unreadClass = n.unread ? 'unread' : '';
    html += `
      <div class="notification-item ${unreadClass}">
        <span class="notification-item-icon">${n.icon}</span>
        <div class="notification-item-content">
          <strong style="font-weight: 700; color: white;">${escapeHtml(n.title)}</strong>
          <span style="color: var(--text-secondary); margin-top: 2px;">${escapeHtml(n.message)}</span>
          <span class="notification-item-time">${formatDateTime(n.timestamp)}</span>
        </div>
      </div>
    `;
  });
  notificationsList.innerHTML = html;
}

function markAllNotificationsAsRead() {
  const readIds = JSON.parse(localStorage.getItem('read_notification_ids') || '[]');
  notifications.forEach(n => {
    if (n.unread) {
      n.unread = false;
      readIds.push(n.id);
    }
  });
  localStorage.setItem('read_notification_ids', JSON.stringify(readIds));
  renderNotificationsList();
}

// Check URL query parameters
function checkUrlParams() {
  const urlParams = new URLSearchParams(window.location.search);
  const success = urlParams.get('success');
  const error = urlParams.get('error');
  const errorMsg = urlParams.get('msg');

  if (success) {
    showAlert(`Successfully connected to ${success.toUpperCase()}!`, 'success');
  } else if (error) {
    showAlert(`Connection failed: ${errorMsg || error}`, 'danger');
  }

  if (success || error) {
    window.history.replaceState({}, document.title, window.location.pathname);
  }
}

// ----------------------------------------------------
// Load Default Preset Settings
// ----------------------------------------------------
async function fetchSettings() {
  try {
    const res = await fetch('/api/settings');
    defaultSettings = await res.json();
    
    // Fill presets form
    if (defaultTagsInput) defaultTagsInput.value = defaultSettings.defaultTags || '';
    if (defaultDescTextarea) defaultDescTextarea.value = defaultSettings.defaultDescription || '';
    
    // Update dashboard read-only presets display
    const dbPresetTags = document.getElementById('dashboard-preset-tags');
    const dbPresetDesc = document.getElementById('dashboard-preset-desc');
    if (dbPresetTags) dbPresetTags.textContent = defaultSettings.defaultTags || 'None';
    if (dbPresetDesc) dbPresetDesc.textContent = defaultSettings.defaultDescription || 'None';
    
    // Prefill main composer fields
    const tagsInput = document.getElementById('tags-input');
    const descInput = document.getElementById('desc-input');
    if (tagsInput) tagsInput.value = defaultSettings.defaultTags || '';
    if (descInput) descInput.value = defaultSettings.defaultDescription || '';
    
    // Prefill dropdown settings
    const ytPrivacy = document.getElementById('yt-privacy');
    const ytCategory = document.getElementById('yt-category');
    const wpStatusSelect = document.getElementById('wp-status-select');
    
    if (ytPrivacy && defaultSettings.youtubePrivacy) {
      ytPrivacy.value = defaultSettings.youtubePrivacy;
    }
    if (ytCategory && defaultSettings.youtubeCategory) {
      ytCategory.value = defaultSettings.youtubeCategory;
    }
    if (wpStatusSelect && defaultSettings.wordpressStatus) {
      wpStatusSelect.value = defaultSettings.wordpressStatus;
    }
  } catch (err) {
    console.error('Failed to load settings presets:', err);
  }
}

if (presetsForm) {
  presetsForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const payload = {
      defaultTags: defaultTagsInput ? defaultTagsInput.value : '',
      defaultDescription: defaultDescTextarea ? defaultDescTextarea.value : ''
    };

    try {
      const res = await fetch('/api/settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (res.ok && data.success) {
        showAlert('Default presets settings saved successfully!');
        defaultSettings = data.settings;
        
        // Update composer fields immediately!
        const tagsInput = document.getElementById('tags-input');
        const descInput = document.getElementById('desc-input');
        if (tagsInput) tagsInput.value = defaultSettings.defaultTags || '';
        if (descInput) descInput.value = defaultSettings.defaultDescription || '';
        
        // Update dashboard read-only presets display
        const dbPresetTags = document.getElementById('dashboard-preset-tags');
        const dbPresetDesc = document.getElementById('dashboard-preset-desc');
        if (dbPresetTags) dbPresetTags.textContent = defaultSettings.defaultTags || 'None';
        if (dbPresetDesc) dbPresetDesc.textContent = defaultSettings.defaultDescription || 'None';
        
        // Sync settings tab fields
        const defaultTagsTab = document.getElementById('default-tags-tab');
        const defaultDescTab = document.getElementById('default-desc-tab');
        if (defaultTagsTab) defaultTagsTab.value = defaultSettings.defaultTags || '';
        if (defaultDescTab) defaultDescTab.value = defaultSettings.defaultDescription || '';
      } else {
        showAlert(data.error || 'Failed to save settings.', 'danger');
      }
    } catch (err) {
      showAlert('Server connection error. Failed to save settings.', 'danger');
    }
  });
}

// ----------------------------------------------------
// Manage Accounts & Channel links
// ----------------------------------------------------
async function fetchAccounts() {
  try {
    const res = await fetch('/api/accounts');
    linkedAccounts = await res.json();
    updateAccountsUI();
    updateTelemetryStats();
    syncSettingsTabUI();
  } catch (err) {
    console.error('Failed to fetch account status:', err);
  }
}

function updateAccountsUI() {
  const cardYt = document.getElementById('channel-youtube');
  const cardIg = document.getElementById('channel-instagram');
  const cardWp = document.getElementById('channel-wordpress');

  // YouTube
  if (linkedAccounts.youtube?.linked) {
    if (ytStatus) {
      ytStatus.innerHTML = `<span class="dot-indicator connected">●</span> Connected: ${escapeHtml(linkedAccounts.youtube.accountName)}`;
      ytStatus.classList.add('connected');
    }
    if (cardYt) cardYt.classList.add('connected');
    if (ytLinkBtn) ytLinkBtn.classList.add('hidden');
    if (ytUnlinkBtn) ytUnlinkBtn.classList.remove('hidden');
    
    if (selectorYtVideo) selectorYtVideo.disabled = false;
    if (selectorYtShorts) selectorYtShorts.disabled = false;
    if (selectorYtPost) selectorYtPost.disabled = false;
    if (labelYtVideo) labelYtVideo.classList.remove('disabled');
    if (labelYtShorts) labelYtShorts.classList.remove('disabled');
    if (labelYtPost) labelYtPost.classList.remove('disabled');
  } else {
    if (ytStatus) {
      ytStatus.innerHTML = `<span class="dot-indicator disconnected">●</span> Not Connected`;
      ytStatus.classList.remove('connected');
    }
    if (cardYt) cardYt.classList.remove('connected');
    if (ytLinkBtn) ytLinkBtn.classList.remove('hidden');
    if (ytUnlinkBtn) ytUnlinkBtn.classList.add('hidden');
    
    if (selectorYtVideo) { selectorYtVideo.disabled = true; selectorYtVideo.checked = false; }
    if (selectorYtShorts) { selectorYtShorts.disabled = true; selectorYtShorts.checked = false; }
    if (selectorYtPost) { selectorYtPost.disabled = true; selectorYtPost.checked = false; }
    if (labelYtVideo) labelYtVideo.classList.add('disabled');
    if (labelYtShorts) labelYtShorts.classList.add('disabled');
    if (labelYtPost) labelYtPost.classList.add('disabled');
  }

  // Instagram
  if (linkedAccounts.instagram?.linked) {
    if (igStatus) {
      igStatus.innerHTML = `<span class="dot-indicator connected">●</span> Connected: ${escapeHtml(linkedAccounts.instagram.accountName)}`;
      igStatus.classList.add('connected');
    }
    if (cardIg) cardIg.classList.add('connected');
    if (igLinkBtn) igLinkBtn.classList.add('hidden');
    if (igUnlinkBtn) igUnlinkBtn.classList.remove('hidden');
    
    if (selectorIgPost) selectorIgPost.disabled = false;
    if (selectorIgReel) selectorIgReel.disabled = false;
    if (selectorIgStory) selectorIgStory.disabled = false;
    if (labelIgPost) labelIgPost.classList.remove('disabled');
    if (labelIgReel) labelIgReel.classList.remove('disabled');
    if (labelIgStory) labelIgStory.classList.remove('disabled');
  } else {
    if (igStatus) {
      igStatus.innerHTML = `<span class="dot-indicator disconnected">●</span> Not Connected`;
      igStatus.classList.remove('connected');
    }
    if (cardIg) cardIg.classList.remove('connected');
    if (igLinkBtn) igLinkBtn.classList.remove('hidden');
    if (igUnlinkBtn) igUnlinkBtn.classList.add('hidden');
    
    if (selectorIgPost) { selectorIgPost.disabled = true; selectorIgPost.checked = false; }
    if (selectorIgReel) { selectorIgReel.disabled = true; selectorIgReel.checked = false; }
    if (selectorIgStory) { selectorIgStory.disabled = true; selectorIgStory.checked = false; }
    if (labelIgPost) labelIgPost.classList.add('disabled');
    if (labelIgReel) labelIgReel.classList.add('disabled');
    if (labelIgStory) labelIgStory.classList.add('disabled');
  }

  // WordPress
  if (linkedAccounts.wordpress?.linked) {
    if (wpStatus) {
      wpStatus.innerHTML = `<span class="dot-indicator connected">●</span> Connected: ${escapeHtml(linkedAccounts.wordpress.accountName)}`;
      wpStatus.classList.add('connected');
    }
    if (cardWp) cardWp.classList.add('connected');
    if (wpLinkBtn) wpLinkBtn.classList.add('hidden');
    if (wpUnlinkBtn) wpUnlinkBtn.classList.remove('hidden');
    
    if (selectorWp) selectorWp.disabled = false;
    if (labelWp) labelWp.classList.remove('disabled');
  } else {
    if (wpStatus) {
      wpStatus.innerHTML = `<span class="dot-indicator disconnected">●</span> Not Connected`;
      wpStatus.classList.remove('connected');
    }
    if (cardWp) cardWp.classList.remove('connected');
    if (wpLinkBtn) wpLinkBtn.classList.remove('hidden');
    if (wpUnlinkBtn) wpUnlinkBtn.classList.add('hidden');
    
    if (selectorWp) { selectorWp.disabled = true; selectorWp.checked = false; }
    if (labelWp) labelWp.classList.add('disabled');
  }
}

async function handleUnlink(platform) {
  if (!confirm(`Are you sure you want to disconnect ${platform}?`)) {
    return;
  }
  try {
    const res = await fetch('/api/accounts/unlink', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ platform })
    });
    const result = await res.json();
    if (result.success) {
      showAlert(`${platform.toUpperCase()} unlinked successfully.`);
      await fetchAccounts();
    } else {
      showAlert(`Unlink failed: ${result.error}`, 'danger');
    }
  } catch (err) {
    showAlert('Server error during unlink.', 'danger');
  }
}

if (ytUnlinkBtn) ytUnlinkBtn.addEventListener('click', () => handleUnlink('youtube'));
if (igUnlinkBtn) igUnlinkBtn.addEventListener('click', () => handleUnlink('instagram'));
if (wpUnlinkBtn) wpUnlinkBtn.addEventListener('click', () => handleUnlink('wordpress'));

// WordPress Modal
if (wpLinkBtn) wpLinkBtn.addEventListener('click', () => wpModal.classList.remove('hidden'));
if (wpModalClose) wpModalClose.addEventListener('click', () => wpModal.classList.add('hidden'));

wpLinkFormData.addEventListener('submit', async (e) => {
  e.preventDefault();
  wpSpinner.classList.remove('hidden');
  btnWpSave.querySelector('.btn-text').classList.add('hidden');
  btnWpSave.disabled = true;

  try {
    const res = await fetch('/api/accounts/wordpress', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        url: wpUrlInput.value,
        username: wpUserInput.value,
        appPassword: wpPwInput.value
      })
    });
    const data = await res.json();
    if (res.ok && data.success) {
      showAlert('WordPress account linked successfully!');
      wpModal.classList.add('hidden');
      wpLinkFormData.reset();
      fetchAccounts();
    } else {
      showAlert(data.error || 'Failed to verify WordPress site.', 'danger');
    }
  } catch (err) {
    showAlert('WordPress Connection Error.', 'danger');
  } finally {
    wpSpinner.classList.add('hidden');
    btnWpSave.querySelector('.btn-text').classList.remove('hidden');
    btnWpSave.disabled = false;
  }
});

// ----------------------------------------------------
// Media Preview
// ----------------------------------------------------
mediaInput.addEventListener('change', handleFileSelect);

dropzone.addEventListener('dragover', (e) => {
  e.preventDefault();
  dropzone.style.borderColor = 'var(--accent-primary)';
});
dropzone.addEventListener('dragleave', (e) => {
  e.preventDefault();
  dropzone.style.borderColor = 'var(--input-border)';
});
dropzone.addEventListener('drop', (e) => {
  e.preventDefault();
  dropzone.style.borderColor = 'var(--input-border)';
  if (e.dataTransfer.files.length > 0) {
    mediaInput.files = e.dataTransfer.files;
    handleFileSelect();
  }
});

function handleFileSelect() {
  const file = mediaInput.files[0];
  if (!file) return;

  const fileUrl = URL.createObjectURL(file);
  dropzoneText.classList.add('hidden');
  previewContainer.classList.remove('hidden');

  if (file.type.startsWith('image/')) {
    imgPreview.src = fileUrl;
    imgPreview.classList.remove('hidden');
    videoPreview.classList.add('hidden');
  } else if (file.type.startsWith('video/')) {
    videoPreview.src = fileUrl;
    videoPreview.classList.remove('hidden');
    imgPreview.classList.add('hidden');
  }
}

btnRemovePreview.addEventListener('click', (e) => {
  e.stopPropagation();
  mediaInput.value = '';
  dropzoneText.classList.remove('hidden');
  previewContainer.classList.add('hidden');
  imgPreview.src = '';
  videoPreview.src = '';
});

// ----------------------------------------------------
// Submit / Publish Composer Form
// ----------------------------------------------------
composerForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  
  if (!mediaInput.files[0]) {
    showAlert('Please select a media file to upload.', 'danger');
    return;
  }

  const checkedCheckboxes = Array.from(document.querySelectorAll('input[name="platforms"]:checked'));
  const selectedPlatforms = checkedCheckboxes.map(cb => cb.value);
  
  if (selectedPlatforms.length === 0) {
    showAlert('Please select at least one publish destination.', 'danger');
    return;
  }

  btnPublish.disabled = true;
  publishSpinner.classList.remove('hidden');
  btnPublish.querySelector('.btn-text').classList.add('hidden');

  const formData = new FormData();
  formData.append('media', mediaInput.files[0]);
  formData.append('title', document.getElementById('title-input').value);
  formData.append('description', document.getElementById('desc-input').value);
  formData.append('tags', document.getElementById('tags-input').value);
  formData.append('platforms', JSON.stringify(selectedPlatforms));

  // Platform specific override configurations
  formData.append('youtubePrivacy', document.getElementById('yt-privacy').value);
  formData.append('youtubeCategory', document.getElementById('yt-category').value);
  formData.append('wordpressStatus', document.getElementById('wp-status-select').value);

  // Scheduling timestamp
  const scheduledTime = scheduleInput.value;
  if (scheduledTime) {
    formData.append('scheduledAt', new Date(scheduledTime).toISOString());
  }

  try {
    const res = await fetch('/api/publish', {
      method: 'POST',
      body: formData
    });
    const result = await res.json();
    
    if (res.ok && result.success) {
      showAlert(result.message || 'Job successfully queued!');
      
      // Reset main fields
      document.getElementById('title-input').value = '';
      document.getElementById('desc-input').value = defaultSettings.defaultDescription || '';
      document.getElementById('tags-input').value = defaultSettings.defaultTags || '';
      scheduleInput.value = '';
      btnRemovePreview.click();
      
      // Reset Wizard Step
      goToStep(1);
      
      activeJobId = result.jobId;
      fetchJobs(true);
      fetchLogs(activeJobId, true);
    } else {
      showAlert(result.error || 'Failed to submit publish job.', 'danger');
    }
  } catch (err) {
    showAlert('Failed to connect to publishing API.', 'danger');
  } finally {
    btnPublish.disabled = false;
    publishSpinner.classList.add('hidden');
    btnPublish.querySelector('.btn-text').classList.remove('hidden');
  }
});

// ----------------------------------------------------
// Jobs List & Logs Pane
// ----------------------------------------------------
async function fetchJobs(showSpinner = true) {
  try {
    const res = await fetch('/api/jobs');
    currentJobsList = await res.json();
    renderJobsList(currentJobsList);
    updateTelemetryStats();
    updateNotificationsFromJobs(currentJobsList);
  } catch (err) {
    console.error('Failed to fetch job history:', err);
  }
}

function renderJobsList(jobs) {
  if (jobs.length === 0) {
    jobsList.innerHTML = '<p class="empty-state">No jobs processed yet.</p>';
    return;
  }

  let html = '';
  jobs.forEach(job => {
    const isSelected = job.id === activeJobId ? 'selected' : '';
    const date = formatDateTime(job.createdAt);
    const channelsHtml = Object.keys(job.destinations).map(ch => {
      return `<span class="channel-tag">${ch.toUpperCase()}</span>`;
    }).join(' ');

    const creatorInfo = job.createdBy ? `<span style="opacity:0.6; font-size:0.7rem; margin-right:6px;">By: ${job.createdBy}</span>` : '';

    html += `
      <div class="job-list-item ${isSelected}" data-id="${job.id}">
        <div class="job-item-header">
          <span class="job-item-title">${escapeHtml(job.title)}</span>
          <span class="status-badge status-${job.status.toLowerCase()}">${job.status}</span>
        </div>
        <div class="job-item-details">
          <div class="job-item-channels">${channelsHtml}</div>
          <div>
            ${creatorInfo}
            <span>${date}</span>
          </div>
        </div>
      </div>
    `;
  });

  jobsList.innerHTML = html;

  document.querySelectorAll('.job-list-item').forEach(item => {
    item.addEventListener('click', () => {
      activeJobId = item.getAttribute('data-id');
      
      document.querySelectorAll('.job-list-item').forEach(i => i.classList.remove('selected'));
      item.classList.add('selected');
      
      fetchLogs(activeJobId, true);
      updateSharePanelForActiveJob();
    });
  });
}

async function fetchLogs(jobId, showSpinner = true) {
  if (showSpinner) {
    logsPane.innerHTML = '<div class="empty-state"><div class="spinner"></div><p style="margin-top:8px">Loading logs...</p></div>';
  }
  try {
    const res = await fetch(`/api/logs?jobId=${jobId}`);
    const logs = await res.json();
    renderLogs(logs);
  } catch (err) {
    logsPane.innerHTML = '<p class="empty-state">Error fetching logs.</p>';
  }
}

function renderLogs(logs) {
  if (logs.length === 0) {
    logsPane.innerHTML = '<p class="empty-state">No logs recorded for this job.</p>';
    if (activityTimeline) {
      activityTimeline.innerHTML = '<p class="empty-state">Select a history item or submit a post to view activity.</p>';
    }
    return;
  }

  // Populate technical console logs
  const sortedLogs = [...logs].reverse();
  let html = '';
  sortedLogs.forEach(log => {
    const time = new Date(log.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    const platform = log.platform.toUpperCase();
    
    html += `
      <div class="log-line">
        <span class="log-time">[${time}]</span>
        <span class="log-plat">${platform}:</span>
        <span class="log-msg-${log.type}">${escapeHtml(log.message)}</span>
      </div>
    `;
  });
  logsPane.innerHTML = html;
  logsPane.scrollTop = logsPane.scrollHeight;

  // Populate clean activity timeline (oldest first, so chronological flow)
  if (activityTimeline) {
    let timelineHtml = '';
    const chronologicalLogs = [...logs];
    chronologicalLogs.forEach(log => {
      const time = formatDateTime(log.timestamp);
      
      let iconClass = 'info';
      let iconChar = 'ℹ';
      
      if (log.type === 'success') {
        iconClass = 'success';
        iconChar = '✓';
      } else if (log.type === 'error' || log.type === 'danger') {
        iconClass = 'error';
        iconChar = '✕';
      }
      
      const displayMsg = log.platform && log.platform !== 'system' 
        ? `<strong style="text-transform: capitalize; color: var(--accent-primary);">${log.platform}:</strong> ${escapeHtml(log.message)}`
        : escapeHtml(log.message);
      
      timelineHtml += `
        <div class="activity-item">
          <div class="activity-status-icon ${iconClass}">${iconChar}</div>
          <span class="activity-text">${displayMsg}</span>
          <span class="activity-time">${time}</span>
        </div>
      `;
    });
    activityTimeline.innerHTML = timelineHtml;
    activityTimeline.scrollTop = activityTimeline.scrollHeight;
  }
}

// ----------------------------------------------------
// Social Sharing Shortcuts & Permalinks
// ----------------------------------------------------
function updateSharePanelForActiveJob() {
  if (!activeJobId) {
    sharePanel.classList.add('hidden');
    return;
  }

  const job = currentJobsList.find(j => j.id === activeJobId);
  if (!job) return;

  const destinations = job.destinations;
  let hasCompletedDest = false;
  let shareHtml = '';

  Object.keys(destinations).forEach(platform => {
    const dest = destinations[platform];
    
    if (dest.status === 'COMPLETED' && dest.externalId && dest.externalId.startsWith('http')) {
      hasCompletedDest = true;
      const link = dest.externalId;
      
      // WhatsApp Share Link
      const waText = `Check out my new post: ${link}`;
      const waUrl = `https://api.whatsapp.com/send?text=${encodeURIComponent(waText)}`;
      
      shareHtml += `
        <div style="background:rgba(255,255,255,0.02); padding:10px 14px; border:1px solid rgba(255,255,255,0.04); border-radius:10px; display:flex; flex-direction:column; gap:6px;">
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <span style="font-weight:600; font-size:0.85rem;">${platform.toUpperCase()} Post Link:</span>
            <a href="${link}" target="_blank" class="btn-share" style="background:var(--accent-primary)">🔗 View Live</a>
          </div>
          <div class="share-btn-group">
            <a href="${waUrl}" target="_blank" class="btn-share wa">💬 Share to WhatsApp</a>
            <button class="btn-share" onclick="navigator.clipboard.writeText('${link}'); alert('Link copied to clipboard!');">📋 Copy Link</button>
          </div>
          ${platform === 'instagram' ? `
            <span style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;">
              💡 <strong>Story Tip:</strong> Copy link, open Instagram app on your phone, create a Story, and use the <strong>Link Sticker</strong> to paste this URL.
            </span>
          ` : ''}
        </div>
      `;
    }
  });

  if (hasCompletedDest) {
    shareLinksContainer.innerHTML = shareHtml;
    sharePanel.classList.remove('hidden');
  } else {
    sharePanel.classList.add('hidden');
  }
}

btnRefreshJobs.addEventListener('click', () => {
  fetchJobs();
  if (activeJobId) {
    fetchLogs(activeJobId);
  }
});

// ----------------------------------------------------
// Multi-step Wizard Navigation & Logic
// ----------------------------------------------------
let currentStep = 1;

function goToStep(step) {
  currentStep = step;
  
  // Toggle visibility of step elements
  document.querySelectorAll('.form-step').forEach((el) => {
    el.classList.remove('active');
  });
  const activeStepEl = document.getElementById(`step-${step}`);
  if (activeStepEl) activeStepEl.classList.add('active');

  // Update step indicators styling
  for (let i = 1; i <= 3; i++) {
    const indicator = document.getElementById(`ind-step-${i}`);
    if (!indicator) continue;
    if (i < step) {
      indicator.className = 'step-indicator completed';
    } else if (i === step) {
      indicator.className = 'step-indicator active';
    } else {
      indicator.className = 'step-indicator';
    }
  }

  // Update progress line width
  const progressLine = document.getElementById('step-progress-line');
  if (progressLine) {
    const percentage = step === 1 ? 0 : step === 2 ? 50 : 100;
    progressLine.style.width = `${percentage}%`;
  }
}

// Step 1 Validation & Next
if (btnNext1) {
  btnNext1.addEventListener('click', () => {
    if (!mediaInput.files || !mediaInput.files[0]) {
      showAlert('Please upload a media file to proceed.', 'danger');
      return;
    }
    goToStep(2);
  });
}

// Step 2 Validation & Next / Back
if (btnNext2) {
  btnNext2.addEventListener('click', () => {
    const titleVal = document.getElementById('title-input').value.trim();
    const descVal = document.getElementById('desc-input').value.trim();
    
    if (!titleVal) {
      showAlert('Post title / video title is required.', 'danger');
      document.getElementById('title-input').focus();
      return;
    }
    if (!descVal) {
      showAlert('Post description / caption is required.', 'danger');
      document.getElementById('desc-input').focus();
      return;
    }
    goToStep(3);
  });
}

if (btnPrev2) {
  btnPrev2.addEventListener('click', () => {
    goToStep(1);
  });
}

// Step 3 Back
if (btnPrev3) {
  btnPrev3.addEventListener('click', () => {
    goToStep(2);
  });
}

// ----------------------------------------------------
// SaaS Telemetry Stats Engine
// ----------------------------------------------------
function updateTelemetryStats() {
  const publishedVal = document.getElementById('stats-published');
  const connectedVal = document.getElementById('stats-connected');
  const scheduledVal = document.getElementById('stats-scheduled');
  const failedVal = document.getElementById('stats-failed');

  if (publishedVal) {
    const publishedCount = currentJobsList.filter(j => j.status === 'COMPLETED').length;
    publishedVal.textContent = publishedCount;
  }
  if (connectedVal) {
    let connectedCount = 0;
    if (linkedAccounts.youtube?.linked) connectedCount++;
    if (linkedAccounts.instagram?.linked) connectedCount++;
    if (linkedAccounts.wordpress?.linked) connectedCount++;
    connectedVal.textContent = connectedCount;
  }
  if (scheduledVal) {
    const scheduledCount = currentJobsList.filter(j => j.status === 'SCHEDULED').length;
    scheduledVal.textContent = scheduledCount;
  }
  if (failedVal) {
    const failedCount = currentJobsList.filter(j => j.status === 'FAILED').length;
    failedVal.textContent = failedCount;
  }
}

function escapeHtml(str) {
  if (!str) return '';
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

// ----------------------------------------------------
// Navigation Tab Switcher Logic
// ----------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
  const tabLinks = document.querySelectorAll('.header-nav .nav-link');
  const tabContents = document.querySelectorAll('.tab-content');

  if (tabLinks && tabContents) {
    tabLinks.forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const targetTab = link.getAttribute('data-tab');
        if (!targetTab) return;
        
        // Update navigation links state
        tabLinks.forEach(l => l.classList.remove('active'));
        link.classList.add('active');
        
        // Toggle corresponding container visibility
        tabContents.forEach(content => {
          if (content.id === `tab-${targetTab}`) {
            content.classList.remove('hidden');
          } else {
            content.classList.add('hidden');
          }
        });
        
        // Populate tab-specific data
        if (targetTab === 'campaigns') {
          renderCampaignsTable();
        } else if (targetTab === 'analytics') {
          renderAnalyticsTab();
        } else if (targetTab === 'settings') {
          syncSettingsTabUI();
        }
      });
    });
  }

  // Campaigns Filter Registry
  const campaignsSearch = document.getElementById('campaigns-search');
  if (campaignsSearch) {
    campaignsSearch.addEventListener('input', () => {
      const q = campaignsSearch.value.toLowerCase().trim();
      const tableBody = document.getElementById('campaigns-table-body');
      if (!tableBody) return;
      
      const filtered = currentJobsList.filter(job => 
        job.id.toLowerCase().includes(q) || 
        job.title.toLowerCase().includes(q) ||
        (job.createdBy && job.createdBy.toLowerCase().includes(q))
      );
      
      if (filtered.length === 0) {
        tableBody.innerHTML = `
          <tr>
            <td colspan="7" style="text-align: center; padding: 24px; color: var(--text-muted);">No matching campaigns found.</td>
          </tr>
        `;
        return;
      }
      
      let html = '';
      filtered.forEach(job => {
        const date = formatDateTime(job.createdAt);
        const sched = job.scheduledAt ? formatDateTime(job.scheduledAt) : 'Immediate';
        const channelsHtml = Object.keys(job.destinations).map(ch => {
          return `<span class="channel-tag">${ch.toUpperCase()}</span>`;
        }).join(' ');
        
        html += `
          <tr style="border-bottom: 1px solid rgba(255,255,255,0.04);">
            <td style="padding: 12px 8px; font-family: monospace; opacity: 0.8;">${job.id}</td>
            <td style="padding: 12px 8px; font-weight: 500;">${escapeHtml(job.title)}</td>
            <td style="padding: 12px 8px;">${channelsHtml}</td>
            <td style="padding: 12px 8px; color: var(--text-secondary);">${sched}</td>
            <td style="padding: 12px 8px; color: var(--text-secondary);">${job.createdBy || 'Unknown'}</td>
            <td style="padding: 12px 8px; color: var(--text-secondary);">${date}</td>
            <td style="padding: 12px 8px;"><span class="status-badge status-${job.status.toLowerCase()}">${job.status}</span></td>
          </tr>
        `;
      });
      tableBody.innerHTML = html;
    });
  }

  // Campaigns Refresh Trigger
  const btnRefreshCampaigns = document.getElementById('btn-refresh-campaigns');
  if (btnRefreshCampaigns) {
    btnRefreshCampaigns.addEventListener('click', () => {
      fetchJobs().then(() => renderCampaignsTable());
    });
  }

  // Settings Tab Channels Click Event Delegation
  const settingsChannelsList = document.getElementById('settings-channels-list');
  if (settingsChannelsList) {
    settingsChannelsList.addEventListener('click', (e) => {
      const target = e.target;
      if (target.classList.contains('btn-unlink-tab')) {
        const platform = target.getAttribute('data-platform');
        handleUnlink(platform);
      } else if (target.classList.contains('btn-link-wp-tab')) {
        if (wpModal) wpModal.classList.remove('hidden');
      }
    });
  }
});

function renderCampaignsTable() {
  const tableBody = document.getElementById('campaigns-table-body');
  if (!tableBody) return;
  
  if (currentJobsList.length === 0) {
    tableBody.innerHTML = `
      <tr>
        <td colspan="7" style="text-align: center; padding: 24px; color: var(--text-muted);">No campaigns processed yet.</td>
      </tr>
    `;
    return;
  }
  
  let html = '';
  currentJobsList.forEach(job => {
    const date = formatDateTime(job.createdAt);
    const sched = job.scheduledAt ? formatDateTime(job.scheduledAt) : 'Immediate';
    const channelsHtml = Object.keys(job.destinations).map(ch => {
      return `<span class="channel-tag">${ch.toUpperCase()}</span>`;
    }).join(' ');
    
    html += `
      <tr style="border-bottom: 1px solid rgba(255,255,255,0.04);">
        <td style="padding: 12px 8px; font-family: monospace; opacity: 0.8;">${job.id}</td>
        <td style="padding: 12px 8px; font-weight: 500;">${escapeHtml(job.title)}</td>
        <td style="padding: 12px 8px;">${channelsHtml}</td>
        <td style="padding: 12px 8px; color: var(--text-secondary);">${sched}</td>
        <td style="padding: 12px 8px; color: var(--text-secondary);">${job.createdBy || 'Unknown'}</td>
        <td style="padding: 12px 8px; color: var(--text-secondary);">${date}</td>
        <td style="padding: 12px 8px;"><span class="status-badge status-${job.status.toLowerCase()}">${job.status}</span></td>
      </tr>
    `;
  });
  tableBody.innerHTML = html;
}

function renderAnalyticsTab() {
  const ytPct = document.getElementById('analytics-yt-pct');
  const ytBar = document.getElementById('analytics-yt-bar');
  const igPct = document.getElementById('analytics-ig-pct');
  const igBar = document.getElementById('analytics-ig-bar');
  const wpPct = document.getElementById('analytics-wp-pct');
  const wpBar = document.getElementById('analytics-wp-bar');
  
  const successRatio = document.getElementById('analytics-success-ratio');
  const completedCount = document.getElementById('analytics-completed-count');
  const failedCount = document.getElementById('analytics-failed-count');
  const pendingCount = document.getElementById('analytics-pending-count');
  
  if (!ytPct) return;
  
  let ytCount = 0;
  let igCount = 0;
  let wpCount = 0;
  
  let comp = 0;
  let fail = 0;
  let pend = 0;
  
  currentJobsList.forEach(job => {
    const dests = Object.keys(job.destinations);
    dests.forEach(d => {
      if (d.startsWith('youtube')) ytCount++;
      if (d.startsWith('instagram')) igCount++;
      if (d === 'wordpress') wpCount++;
    });
    
    if (job.status === 'COMPLETED') comp++;
    else if (job.status === 'FAILED') fail++;
    else pend++;
  });
  
  const totalDest = ytCount + igCount + wpCount;
  if (totalDest > 0) {
    const ytVal = Math.round((ytCount / totalDest) * 100);
    const igVal = Math.round((igCount / totalDest) * 100);
    const wpVal = 100 - ytVal - igVal;
    
    ytPct.textContent = `${ytVal}%`;
    ytBar.style.width = `${ytVal}%`;
    igPct.textContent = `${igVal}%`;
    igBar.style.width = `${igVal}%`;
    wpPct.textContent = `${wpVal}%`;
    wpBar.style.width = `${wpVal}%`;
  } else {
    ytPct.textContent = '0%';
    ytBar.style.width = '0%';
    igPct.textContent = '0%';
    igBar.style.width = '0%';
    wpPct.textContent = '0%';
    wpBar.style.width = '0%';
  }
  
  const totalJobs = currentJobsList.length;
  if (totalJobs > 0) {
    const successVal = Math.round((comp / totalJobs) * 100);
    successRatio.textContent = `${successVal}%`;
  } else {
    successRatio.textContent = '0%';
  }
  
  completedCount.textContent = comp;
  failedCount.textContent = fail;
  pendingCount.textContent = pend;
}

function syncSettingsTabUI() {
  const defaultTagsTab = document.getElementById('default-tags-tab');
  const defaultDescTab = document.getElementById('default-desc-tab');
  const settingsChannelsList = document.getElementById('settings-channels-list');
  
  if (defaultTagsTab) {
    defaultTagsTab.value = defaultSettings.defaultTags || '';
  }
  if (defaultDescTab) {
    defaultDescTab.value = defaultSettings.defaultDescription || '';
  }
  
  if (settingsChannelsList) {
    let html = '';
    const platforms = [
      { 
        id: 'youtube', 
        name: 'YouTube', 
        linked: linkedAccounts.youtube?.linked, 
        nameVal: linkedAccounts.youtube?.accountName,
        svg: `<svg class="brand-svg-icon" viewBox="0 0 24 24"><path d="M23.498 6.163a3.003 3.003 0 0 0-2.11-2.11C19.517 3.545 12 3.545 12 3.545s-7.517 0-9.388.508a3.003 3.003 0 0 0-2.11 2.11C0 8.033 0 12 0 12s0 3.967.502 5.837a3.003 3.003 0 0 0 2.11 2.11c1.871.508 9.388.508 9.388.508s7.517 0 9.388-.508a3.003 3.003 0 0 0 2.11-2.11C24 15.967 24 12 24 12s0-3.967-.502-5.837zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>`,
        iconClass: 'yt'
      },
      { 
        id: 'instagram', 
        name: 'Instagram', 
        linked: linkedAccounts.instagram?.linked, 
        nameVal: linkedAccounts.instagram?.accountName,
        svg: `<svg class="brand-svg-icon stroke-svg" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>`,
        iconClass: 'ig'
      },
      { 
        id: 'wordpress', 
        name: 'WordPress CMS', 
        linked: linkedAccounts.wordpress?.linked, 
        nameVal: linkedAccounts.wordpress?.accountName,
        svg: `<svg class="brand-svg-icon" viewBox="0 0 24 24"><path d="M12.158 12.786l-2.698 7.84c.806.236 1.657.365 2.54.365a9.55 9.55 0 0 0 3.733-.757m-3.575-7.448h.001zm8.016 4.708a9.49 9.49 0 0 0 .563-3.21c0-1.89-.645-3.208-1.2-4.173-.55-.965-1.062-1.799-1.062-2.776 0 1.09.435 1.933.955 2.87.522.937 1.11 1.983 1.11 3.84a8.673 8.673 0 0 1-.366 2.449zm-8.877-10.74l3.18 8.685 1.666-5.023c.427-1.267.755-1.996.755-2.733a3.523 3.523 0 0 0-.256-1.332A9.53 9.53 0 0 0 12 2.408c-.7 0-1.378.077-2.03.22zm-7.669 8.24c0-2.316.92-4.632 2.49-6.31l3.524 9.684-3.486-9.61A9.515 9.515 0 0 0 2.408 12c0 2.449.921 4.688 2.43 6.402zm6.657 7.21l-3.324-9.664H8.71l3.23 9.395-.873 2.54zm.002 0c-.001-.002-1.82-5.289-1.82-5.289l-1.41 4.1h.001c.954.767 2.134 1.189 3.229 1.189zm0 0c.957 0 1.865-.246 2.668-.677l-.842-2.45-1.826 3.127zm.001 0a9.54 9.54 0 0 0 5.176-1.54L13.167 12.03zm-1.823 0h.002a9.538 9.538 0 0 0 4.22-3.12l-1.397-4.062z"/></svg>`,
        iconClass: 'wp'
      }
    ];
    
    const showActions = (currentUser && (currentUser.role === 'admin' || currentUser.role === 'superadmin'));
    
    platforms.forEach(p => {
      const statusText = p.linked 
        ? `<span class="dot-indicator connected">●</span> Connected: ${escapeHtml(p.nameVal)}`
        : `<span class="dot-indicator disconnected">●</span> Not Connected`;
      
      const actionButton = showActions 
        ? (p.linked 
            ? `<button type="button" class="btn btn-danger btn-sm btn-unlink-tab" data-platform="${p.id}">Unlink</button>`
            : (p.id === 'wordpress' 
                ? `<button type="button" class="btn btn-connect btn-sm btn-link-wp-tab">Link</button>`
                : `<a href="/auth/${p.id}" class="btn btn-connect btn-sm">Link</a>`
              )
          )
        : '';
        
      html += `
        <div class="settings-channel-card" style="padding: 16px; background: rgba(255,255,255,0.02); border: 1px solid var(--card-border); border-radius: 12px; display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 12px;">
          <div style="display: flex; align-items: center; gap: 12px;">
            <span class="channel-icon ${p.iconClass}">
              ${p.svg}
            </span>
            <div>
              <h3 style="margin: 0; font-size: 0.95rem; font-weight: 600;">${p.name}</h3>
              <p style="margin: 4px 0 0 0; font-size: 0.8rem; display: flex; align-items: center; gap: 6px;">
                ${statusText}
              </p>
            </div>
          </div>
          <div>
            ${actionButton}
          </div>
        </div>
      `;
    });
    settingsChannelsList.innerHTML = html;
  }
}
