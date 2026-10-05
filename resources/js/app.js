const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const page = document.body;

function initializeAccountMenu() {
    const trigger = document.getElementById('account-menu-button');
    const menu = document.getElementById('account-menu');
    if (!trigger || !menu) return;

    const closeMenu = () => {
        menu.classList.add('hidden');
        trigger.setAttribute('aria-expanded', 'false');
    };

    trigger.addEventListener('click', () => {
        const isExpanded = trigger.getAttribute('aria-expanded') === 'true';
        trigger.setAttribute('aria-expanded', String(!isExpanded));
        menu.classList.toggle('hidden', isExpanded);
    });
    menu.addEventListener('click', event => {
        if (event.target.closest('[role="menuitem"]')) closeMenu();
    });
    document.addEventListener('click', event => {
        if (!event.target.closest('.account-menu')) closeMenu();
    });
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape' || trigger.getAttribute('aria-expanded') !== 'true') return;
        closeMenu();
        trigger.focus();
    });
}

function updateThemeToggle(button, isDark) {
    const label = isDark ? 'Switch to light mode' : 'Switch to dark mode';
    const icon = button.querySelector('img');

    button.setAttribute('aria-label', label);
    button.setAttribute('title', label);
    button.setAttribute('aria-pressed', String(isDark));
    icon.src = isDark ? button.dataset.lightIcon : button.dataset.darkIcon;
}

function initializeProfileSettings(notify, onSaved) {
    const profileForm = document.getElementById('profile-form');
    if (!profileForm) return;

    const profileAction = document.getElementById('profile-avatar-action');
    const profilePreset = document.getElementById('profile-avatar-preset');
    const profilePhoto = document.getElementById('profile-preview-image');
    const profileInitial = document.getElementById('profile-preview-initial');
    const profileFile = document.getElementById('profile-avatar-file');
    const profileSaveStatus = document.getElementById('profile-save-status');
    const accountAvatarImage = document.getElementById('user-avatar-image');
    const accountAvatarInitial = document.getElementById('user-avatar-initial');
    let localPreviewUrl = '';
    let profileDetailsChanged = false;

    profilePhoto.referrerPolicy = 'no-referrer';

    function setProfileAvatar(action, photoUrl = '', preset = '', selectionChanged = true) {
        profileAction.value = action;
        profileAction.disabled = !selectionChanged;
        profilePreset.value = preset;
        profileForm.dataset.avatarAction = action;
        profileForm.dataset.avatarPreset = preset;
        profileFile.required = selectionChanged && action === 'upload';
        document.querySelectorAll('[data-avatar-preset]').forEach(button => {
            const selected = action === 'preset' && button.dataset.avatarPreset === preset;
            button.classList.toggle('is-selected', selected);
            button.setAttribute('aria-pressed', String(selected));
        });
        document.querySelectorAll('[data-profile-avatar-choice]').forEach(button => {
            const selected = button.dataset.profileAvatarChoice === action;
            button.classList.toggle('is-selected', selected);
            button.setAttribute('aria-pressed', String(selected));
        });
        document.getElementById('profile-upload-trigger').classList.toggle('is-selected', action === 'upload' && selectionChanged);
        if (photoUrl) {
            profilePhoto.src = photoUrl;
            profilePhoto.classList.remove('hidden');
            profileInitial.classList.add('hidden');
            accountAvatarImage.src = photoUrl;
            accountAvatarImage.classList.remove('hidden');
            accountAvatarInitial.classList.add('hidden');
        } else {
            profilePhoto.removeAttribute('src');
            profilePhoto.classList.add('hidden');
            profileInitial.classList.remove('hidden');
            accountAvatarImage.removeAttribute('src');
            accountAvatarImage.classList.add('hidden');
            accountAvatarInitial.textContent = (profileForm.elements.name.value.trim() || 'A').charAt(0).toUpperCase();
            accountAvatarInitial.classList.remove('hidden');
        }
        if (selectionChanged) profileSaveStatus.textContent = 'Unsaved changes';
    }

    const initialAvatarAction = profileForm.dataset.avatarAction;
    const initialAvatarPreset = profileForm.dataset.avatarPreset;
    const initialAvatarUrl = page.dataset.profilePhotoUrl || '';
    profileForm.elements.name.addEventListener('input', () => {
        profileDetailsChanged = true;
        document.getElementById('profile-summary-name').textContent = profileForm.elements.name.value;
        profileInitial.textContent = (profileForm.elements.name.value.trim() || 'A').charAt(0).toUpperCase();
        accountAvatarInitial.textContent = (profileForm.elements.name.value.trim() || 'A').charAt(0).toUpperCase();
        profileSaveStatus.textContent = 'Unsaved changes';
    });
    profileForm.elements.description.addEventListener('input', () => {
        profileDetailsChanged = true;
        profileSaveStatus.textContent = 'Unsaved changes';
    });
    document.querySelectorAll('[data-avatar-preset]').forEach(button => button.addEventListener('click', () => {
        profileFile.value = '';
        const preset = button.dataset.avatarPreset;
        setProfileAvatar('preset', `/images/icons/${preset}.png`, preset);
    }));
    document.getElementById('use-google-avatar')?.addEventListener('click', () => {
        profileFile.value = '';
        setProfileAvatar('google', profileForm.dataset.googleAvatarUrl);
    });
    document.getElementById('use-initials-avatar').addEventListener('click', () => {
        profileFile.value = '';
        setProfileAvatar('initials');
    });
    profileFile.addEventListener('change', async () => {
        const file = profileFile.files[0];
        if (!file) return;
        if (localPreviewUrl) URL.revokeObjectURL(localPreviewUrl);
        localPreviewUrl = URL.createObjectURL(file);
        setProfileAvatar('upload', localPreviewUrl);

        if (!navigator.onLine) {
            profileSaveStatus.textContent = 'Photo preview ready. Connect to the internet to upload it.';
            notify('Profile photos can only be uploaded while online.');
            return;
        }

        const uploadButton = document.getElementById('profile-upload-trigger');
        const saveButton = document.getElementById('save-profile');
        profileFile.disabled = true;
        uploadButton.setAttribute('aria-disabled', 'true');
        saveButton.disabled = true;
        saveButton.textContent = 'Uploading photo…';
        profileSaveStatus.textContent = 'Uploading photo…';

        try {
            const formData = new FormData();
            formData.append('avatar_action', 'upload');
            formData.append('avatar_file', file);
            const response = await fetch(profileForm.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: formData,
            });
            const result = await response.json();
            if (!response.ok) {
                throw new Error(result.message || Object.values(result.errors || {})[0]?.[0] || `Request failed (${response.status}).`);
            }

            page.dataset.profilePhotoUrl = result.user.avatar_url || '';
            if (localPreviewUrl) {
                URL.revokeObjectURL(localPreviewUrl);
                localPreviewUrl = '';
            }
            profileFile.value = '';
            setProfileAvatar(result.user.avatar_action, result.user.avatar_url || '', result.user.avatar_preset || '', false);
            profileSaveStatus.textContent = profileDetailsChanged
                ? 'Photo uploaded. Save your other profile changes separately.'
                : 'Photo uploaded and saved.';
            onSaved(result.user);
            notify('Profile photo uploaded');
        } catch (error) {
            profileSaveStatus.textContent = 'Photo upload failed. Save profile to retry.';
            notify(`Photo upload failed: ${error.message}`);
        } finally {
            profileFile.disabled = false;
            uploadButton.removeAttribute('aria-disabled');
            saveButton.disabled = false;
            saveButton.textContent = 'Save profile';
        }
    });
    profileForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (!navigator.onLine) {
            notify('Profile settings can only be changed while online.');
            return;
        }

        const saveButton = document.getElementById('save-profile');
        saveButton.disabled = true;
        saveButton.textContent = 'Saving…';
        try {
            const response = await fetch(profileForm.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: new FormData(profileForm),
            });
            const result = await response.json();
            if (!response.ok) {
                throw new Error(result.message || Object.values(result.errors || {})[0]?.[0] || `Request failed (${response.status}).`);
            }

            page.dataset.userName = result.user.name;
            page.dataset.profilePhotoUrl = result.user.avatar_url || '';
            profileForm.elements.name.value = result.user.name;
            profileForm.elements.description.value = result.user.description || '';
            profileDetailsChanged = false;
            document.getElementById('profile-summary-name').textContent = result.user.name;
            profileInitial.textContent = (result.user.name.trim() || 'A').charAt(0).toUpperCase();
            if (localPreviewUrl) {
                URL.revokeObjectURL(localPreviewUrl);
                localPreviewUrl = '';
            }
            profileFile.value = '';
            setProfileAvatar(result.user.avatar_action, result.user.avatar_url || '', result.user.avatar_preset || '', false);
            profileSaveStatus.textContent = 'Profile saved';
            onSaved(result.user);
            notify('Profile updated');
        } catch (error) {
            notify(error.message);
        } finally {
            saveButton.disabled = false;
            saveButton.textContent = 'Save profile';
        }
    });
    setProfileAvatar(initialAvatarAction, initialAvatarUrl, initialAvatarPreset, false);
}

if (page.classList.contains('landing-page')) {
    page.classList.add('has-reveal-animations');
    const landingSection = page.dataset.landingSection;
    const section = landingSection && document.getElementById(landingSection);
    if (section) {
        window.scrollTo({
            top: window.scrollY + section.getBoundingClientRect().top - 88,
            behavior: 'instant',
        });
    }

    const menuButton = document.getElementById('landing-menu-toggle');
    const navigation = document.getElementById('landing-navigation');
    const aboutCopy = document.querySelector('.landing-about-copy');
    const languageButtons = aboutCopy?.querySelectorAll('[data-language]');

    languageButtons?.forEach(button => button.addEventListener('click', () => {
        const language = button.dataset.language;
        if (language !== 'en' && language !== 'km') return;

        aboutCopy.lang = language;
        aboutCopy.querySelectorAll('[data-copy-en][data-copy-km]').forEach(element => {
            element.textContent = element.dataset[`copy${language === 'en' ? 'En' : 'Km'}`];
        });
        aboutCopy.querySelectorAll('[data-copy-alt-en][data-copy-alt-km]').forEach(image => {
            image.alt = image.dataset[`copyAlt${language === 'en' ? 'En' : 'Km'}`];
        });
        languageButtons.forEach(languageButton => {
            const isActive = languageButton === button;
            languageButton.classList.toggle('is-active', isActive);
            languageButton.setAttribute('aria-pressed', String(isActive));
        });
    }));

    menuButton?.addEventListener('click', () => {
        const isOpen = navigation.classList.toggle('is-open');
        menuButton.setAttribute('aria-expanded', String(isOpen));
    });

    navigation?.querySelectorAll('a').forEach(link => link.addEventListener('click', () => {
        navigation.classList.remove('is-open');
        menuButton?.setAttribute('aria-expanded', 'false');
    }));

    const revealItems = document.querySelectorAll('[data-reveal]');
    if ('IntersectionObserver' in window) {
        const revealObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.12 });

        revealItems.forEach(item => revealObserver.observe(item));
    } else {
        revealItems.forEach(item => item.classList.add('is-visible'));
    }
}

if (page.classList.contains('app-page')) {
    initializeAccountMenu();
    const email = page.dataset.userEmail || '';
    const dbName = `taskflow-${email.toLowerCase()}`;
    const apiBase = '/api';
    const filters = ['all', 'today', 'overdue', 'done'];
    const state = {
        projects: [],
        tasks: [],
        filter: page.dataset.workspaceFilter || 'all',
        projectId: page.dataset.workspaceProjectId ? Number(page.dataset.workspaceProjectId) : null,
        sortAsc: true,
        initialLoading: true,
    };
    const openDb = () => new Promise((resolve, reject) => {
        const request = indexedDB.open(dbName, 1);
        request.onupgradeneeded = () => {
            const db = request.result;
            db.createObjectStore('tasks', { keyPath: 'id' });
            db.createObjectStore('projects', { keyPath: 'id' });
            db.createObjectStore('queue', { keyPath: 'queueId', autoIncrement: true });
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
    const dbReady = openDb();
    const store = async (name, mode, operation) => {
        const db = await dbReady;
        return new Promise((resolve, reject) => {
            const transaction = db.transaction(name, mode);
            const request = operation(transaction.objectStore(name));
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    };
    const all = name => store(name, 'readonly', objectStore => objectStore.getAll());
    const put = (name, item) => store(name, 'readwrite', objectStore => objectStore.put(item));
    const remove = (name, key) => store(name, 'readwrite', objectStore => objectStore.delete(key));
    const clear = name => store(name, 'readwrite', objectStore => objectStore.clear());
    const api = async (url, method = 'GET', body = null) => {
        const response = await fetch(`${apiBase}${url}`, {
            method,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: body ? JSON.stringify(body) : undefined,
        });
        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            const requestError = new Error(error.message || Object.values(error.errors || {})[0]?.[0] || `Request failed (${response.status}).`);
            requestError.status = response.status;
            throw requestError;
        }
        return response.status === 204 ? null : response.json();
    };
    const els = Object.fromEntries([
        'project-nav', 'task-list', 'page-title', 'page-subtitle', 'date-label', 'list-heading', 'visible-count',
        'count-all', 'count-today', 'count-overdue', 'overview-projects', 'overview-all', 'overview-open',
        'overview-today', 'overview-overdue', 'overview-done', 'workspace-dashboard', 'workspace-project-grid',
        'sync-status', 'task-modal', 'project-modal', 'task-form', 'project-form', 'toast',
        'current-section',
    ].map(id => [id.replaceAll('-', '_'), document.getElementById(id)]));
    let toastTimer;

    function notify(message) {
        els.toast.textContent = message;
        els.toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => els.toast.classList.remove('show'), 2600);
    }

    function setSyncStatus(text, kind = '') {
        els.sync_status.className = `sync-status ${kind}`;
        els.sync_status.innerHTML = `<i></i> ${text}`;
    }

    function safe(text = '') {
        return String(text).replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
    }

    function localDate(value) {
        if (!value) return '';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return '';
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}T${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
    }

    function dateKey(value) {
        if (!value) return '';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return '';
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    }

    function dateLabel(value) {
        if (!value) return '';
        const date = new Date(value);
        const now = new Date();
        const today = date.toDateString() === now.toDateString();
        return `${today ? 'Today, ' : ''}${date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}${!today ? ` · ${date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}` : ''}`;
    }

    function filteredTasks() {
        let items = state.tasks.filter(task => !state.projectId || Number(task.project_id) === Number(state.projectId));
        const today = dateKey(new Date());
        if (state.filter === 'today') items = items.filter(task => !task.done && dateKey(task.due_date) === today);
        if (state.filter === 'overdue') items = items.filter(task => !task.done && task.due_date && dateKey(task.due_date) < today);
        if (state.filter === 'done') items = items.filter(task => task.done);
        return items.sort((a, b) => {
            if (a.done !== b.done) return Number(a.done) - Number(b.done);
            if (!a.due_date) return 1;
            if (!b.due_date) return -1;
            return (new Date(a.due_date) - new Date(b.due_date)) * (state.sortAsc ? 1 : -1);
        });
    }

    function render() {
        document.getElementById('user-name').textContent = page.dataset.userName || 'Account';
        document.getElementById('user-email').textContent = email;
        const avatarInitial = (page.dataset.userName || 'A').trim().charAt(0).toUpperCase();
        document.getElementById('user-avatar-initial').textContent = avatarInitial;
        const avatarImage = document.getElementById('user-avatar-image');
        avatarImage.referrerPolicy = 'no-referrer';
        if (page.dataset.profilePhotoUrl) {
            avatarImage.src = page.dataset.profilePhotoUrl;
            avatarImage.classList.remove('hidden');
            document.getElementById('user-avatar-initial').classList.add('hidden');
        } else {
            avatarImage.removeAttribute('src');
            avatarImage.classList.add('hidden');
            document.getElementById('user-avatar-initial').classList.remove('hidden');
        }
        els.date_label.textContent = new Date().toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' }).toUpperCase();
        const tasks = state.tasks;
        const today = dateKey(new Date());
        const todayCount = tasks.filter(task => !task.done && dateKey(task.due_date) === today).length;
        const lateCount = tasks.filter(task => !task.done && task.due_date && dateKey(task.due_date) < today).length;
        const doneCount = tasks.filter(task => task.done).length;
        const openCount = tasks.length - doneCount;
        els.count_all.textContent = String(tasks.filter(task => !task.done).length);
        els.count_today.textContent = String(todayCount);
        els.count_overdue.textContent = String(lateCount);
        els.overview_projects.textContent = String(state.projects.length);
        els.overview_all.textContent = String(tasks.length);
        els.overview_open.textContent = String(openCount);
        els.overview_today.textContent = `${todayCount} ${todayCount === 1 ? 'task' : 'tasks'}`;
        els.overview_overdue.textContent = `${lateCount} ${lateCount === 1 ? 'task' : 'tasks'}`;
        els.overview_done.textContent = `${doneCount} ${doneCount === 1 ? 'task' : 'tasks'}`;

        const activeProject = state.projects.find(project => Number(project.id) === Number(state.projectId));
        const showDashboard = !state.initialLoading && !activeProject && state.filter === 'all';
        els.workspace_dashboard.classList.toggle('hidden', !showDashboard);
        els.project_nav.innerHTML = state.projects.length ? state.projects.map(project => `
            <div class="project-row">
                <a class="nav-item project-item ${activeProject?.id === project.id ? 'active' : ''}" data-project="${project.id}" href="${page.dataset.projectUrlTemplate.replace('__PROJECT_ID__', encodeURIComponent(project.id))}">
                    <img class="project-icon" src="/images/icon-new-project/${encodeURIComponent(project.icon || 'icon1.png')}" alt="">
                    <span>${safe(project.name)}</span><span class="nav-count">${state.tasks.filter(task => Number(task.project_id) === Number(project.id) && !task.done).length}</span>
                </a>
                <button class="icon-button project-edit" data-edit-project="${project.id}" aria-label="Edit ${safe(project.name)}">···</button>
            </div>`).join('') : '<p class="project-empty">No projects yet. Add one to get started.</p>';

        els.workspace_project_grid.innerHTML = state.projects.length ? state.projects.map(project => {
            const projectTasks = tasks.filter(task => Number(task.project_id) === Number(project.id));
            const completedTasks = projectTasks.filter(task => task.done).length;
            const completion = projectTasks.length ? Math.round(completedTasks / projectTasks.length * 100) : 0;
            const projectUrl = page.dataset.projectUrlTemplate.replace('__PROJECT_ID__', encodeURIComponent(project.id));

            return `<article class="workspace-project-card">
                <a class="workspace-project-link" data-project="${project.id}" href="${projectUrl}">
                    <span class="workspace-project-accent" style="background:${safe(project.color)}"></span>
                    <img class="workspace-project-icon" src="/images/icon-new-project/${encodeURIComponent(project.icon || 'icon1.png')}" alt="">
                    <span class="workspace-project-copy"><strong>${safe(project.name)}</strong><small>${projectTasks.length} ${projectTasks.length === 1 ? 'task' : 'tasks'} · ${completedTasks} complete</small>${project.description ? `<small class="workspace-project-description">${safe(project.description)}</small>` : ''}</span>
                </a>
                <div class="workspace-progress" role="progressbar" aria-label="${safe(project.name)} completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${completion}">
                    <span style="width:${completion}%;background:${safe(project.color)}"></span>
                </div>
                <div class="workspace-project-footer"><span>${completion}% complete</span><a data-project="${project.id}" href="${projectUrl}">View tasks</a></div>
            </article>`;
        }).join('') : `<div class="workspace-project-empty">
            <span class="workspace-empty-mark" aria-hidden="true">＋</span>
            <div><strong>Start with a project</strong><p>Projects keep related tasks together and make progress easy to track.</p></div>
            <button class="button primary" type="button" data-dashboard-add-project>Create a project</button>
        </div>`;

        const titles = { all: ['All tasks', 'A clear mind starts with a clear plan.', 'Your tasks'], today: ['Today', 'Make today a good one.', 'Due today'], overdue: ['Overdue', 'A fresh start begins here.', 'Past due'], done: ['Completed', 'Look how far you’ve come.', 'Completed tasks'] };
        const title = activeProject?.name || titles[state.filter][0];
        els.page_title.textContent = title;
        els.current_section.textContent = title;
        els.page_subtitle.textContent = activeProject ? (activeProject.description || 'Everything you’re moving forward.') : titles[state.filter][1];
        els.list_heading.textContent = activeProject ? 'Project tasks' : titles[state.filter][2];
        document.querySelectorAll('.main-nav .nav-item').forEach(button => button.classList.toggle('active', !activeProject && button.dataset.filter === state.filter));

        const visible = filteredTasks();
        els.visible_count.textContent = String(visible.length);
        if (state.initialLoading) {
            els.task_list.setAttribute('aria-busy', 'true');
            els.task_list.innerHTML = `
                <div class="workspace-loading" role="status">
                    <span class="workspace-loading-title">Loading your workspace…</span>
                    <span class="workspace-loading-line"></span>
                    <span class="workspace-loading-line short"></span>
                </div>`;
        } else if (!visible.length) {
            els.task_list.setAttribute('aria-busy', 'false');
            const emptyMessages = {
                all: ['A little space to breathe', 'Add a task and take the first step.', '✦'],
                today: ['Nothing due today', 'You have no tasks scheduled for today.', '◷'],
                overdue: ['You’re all caught up', 'There are no past-due tasks right now.', '◴'],
                done: ['Nothing completed just yet', 'Tasks you finish will find their way here.', '✓'],
            };
            const [emptyTitle, emptyDescription, emptyIcon] = activeProject
                ? ['No project tasks yet', 'Add a task to get this project moving.', '✦']
                : emptyMessages[state.filter];
            els.task_list.innerHTML = `<div class="empty-state"><div class="empty-illustration">${emptyIcon}</div><h3>${emptyTitle}</h3><p>${emptyDescription}</p><button class="button primary" id="empty-add-task">Add a task</button></div>`;
        } else {
            els.task_list.setAttribute('aria-busy', 'false');
            els.task_list.innerHTML = visible.map(task => {
                const project = state.projects.find(item => Number(item.id) === Number(task.project_id));
                const due = dateLabel(task.due_date);
                const late = task.due_date && dateKey(task.due_date) < today && !task.done;
                const dueToday = dateKey(task.due_date) === today && !task.done;
                const subtasks = task.subtasks || [];
                return `<article class="task-row ${task.done ? 'is-done' : ''}" data-task-row="${task.id}">
                    <input class="task-check" type="checkbox" ${task.done ? 'checked' : ''} aria-label="Complete ${safe(task.title)}">
                    <div class="task-main">
                        <div class="task-detail" data-edit-task="${task.id}"><span class="task-title">${safe(task.title)}</span>${task.notes ? `<span class="task-notes">${safe(task.notes)}</span>` : ''}</div>
                        ${subtasks.length ? `<ul class="task-subtasks">${subtasks.map((subtask, index) => `<li><label><input class="subtask-check" type="checkbox" data-subtask-index="${index}" ${subtask.done ? 'checked' : ''} aria-label="Complete subtask ${safe(subtask.title)}"><span class="${subtask.done ? 'is-done' : ''}">${safe(subtask.title)}</span></label></li>`).join('')}</ul>` : ''}
                    </div>
                    <div class="task-meta">${!activeProject && project ? `<span class="project-tag"><i class="project-dot" style="background:${safe(project.color)}"></i>${safe(project.name)}</span>` : ''}${due ? `<span class="task-due ${late ? 'late' : dueToday ? 'today' : ''}">${safe(due)}</span>` : ''}<i class="priority-dot ${safe(task.priority)}" title="${safe(task.priority)} priority"></i><button class="icon-button row-more" data-edit-task="${task.id}" aria-label="Edit ${safe(task.title)}">···</button></div>
                </article>`;
            }).join('');
        }
        document.querySelectorAll('[name="project_id"]').forEach(select => {
            const selected = select.value || String(activeProject?.id || state.projects[0]?.id || '');
            select.innerHTML = state.projects.map(project => `<option value="${project.id}">${safe(project.name)}</option>`).join('');
            select.value = selected;
        });
    }

    async function cacheRemote() {
        setSyncStatus('Syncing…', 'syncing');
        try {
            const [projectResult, taskResult] = await Promise.all([api('/projects'), api('/tasks')]);
            state.projects = projectResult.data;
            state.tasks = taskResult.data;
        } catch (error) {
            if (error.status === 401 || error.status === 403) {
                state.projects = [];
                state.tasks = [];
                state.initialLoading = false;
                render();
                setSyncStatus('Sign in again to sync', 'offline');
                notify(error.message);
                return;
            }

            try {
                [state.projects, state.tasks] = await Promise.all([all('projects'), all('tasks')]);
                state.initialLoading = false;
                setSyncStatus(navigator.onLine ? 'Sync needs attention' : 'Working offline', 'offline');
                if (navigator.onLine) notify(`Could not refresh workspace data: ${error.message}`);
            } catch (storageError) {
                state.projects = [];
                state.tasks = [];
                state.initialLoading = false;
                setSyncStatus('Workspace could not load', 'offline');
                notify(`Could not load workspace data: ${storageError.message}`);
            }

            render();
            return;
        }

        state.initialLoading = false;
        render();

        try {
            await clear('projects');
            await clear('tasks');
            for (const project of state.projects) await put('projects', project);
            for (const task of state.tasks) await put('tasks', task);
        } catch (error) {
            notify(`Workspace loaded, but offline data could not be saved: ${error.message}`);
        }

        try {
            await syncQueue();
            if ((await all('queue')).length === 0) setSyncStatus('All changes saved');
        } catch (error) {
            setSyncStatus('Sync needs attention', 'offline');
            notify(`Could not sync saved changes: ${error.message}`);
        }
    }

    async function enqueue(projectId, method, taskId, body) {
        const task = taskId ? state.tasks.find(item => String(item.id) === String(taskId)) : null;
        if (method === 'POST') {
            const id = `local-${crypto.randomUUID()}`;
            const item = { id, project_id: Number(projectId), done: false, priority: 'medium', ...body };
            state.tasks.unshift(item);
            await put('tasks', item);
            await store('queue', 'readwrite', queue => queue.add({ projectId: Number(projectId), method: 'POST', taskId: id, body }));
        } else if (method === 'PATCH' && task) {
            const item = { ...task, ...body };
            state.tasks = state.tasks.map(current => String(current.id) === String(taskId) ? item : current);
            await put('tasks', item);
            await store('queue', 'readwrite', queue => queue.add({ projectId: Number(projectId), method, taskId, body }));
        } else if (method === 'DELETE' && task) {
            state.tasks = state.tasks.filter(current => String(current.id) !== String(taskId));
            await remove('tasks', taskId);
            await store('queue', 'readwrite', queue => queue.add({ projectId: Number(projectId), method, taskId, body: null }));
        }
        render();
        setSyncStatus('Changes waiting to sync', 'offline');
    }

    async function syncQueue() {
        if (!navigator.onLine) return;
        const queue = await all('queue');
        if (!queue.length) return;
        setSyncStatus('Syncing…', 'syncing');
        const ids = new Map();
        for (const entry of queue) {
            let taskId = ids.get(entry.taskId) || entry.taskId;
            if (entry.taskId?.startsWith('local-') && entry.method !== 'POST' && !ids.has(entry.taskId)) continue;
            try {
                let result;
                if (entry.method === 'POST') {
                    result = await api(`/projects/${entry.projectId}/tasks`, 'POST', entry.body);
                    ids.set(entry.taskId, result.data.id);
                    const localIndex = state.tasks.findIndex(task => String(task.id) === entry.taskId);
                    if (localIndex === -1) state.tasks.unshift(result.data);
                    else state.tasks[localIndex] = result.data;
                    await remove('tasks', entry.taskId);
                    await put('tasks', result.data);
                } else if (entry.method === 'PATCH') {
                    result = await api(`/projects/${entry.projectId}/tasks/${taskId}`, 'PATCH', entry.body);
                    const taskIndex = state.tasks.findIndex(task => String(task.id) === String(taskId));
                    if (taskIndex === -1) state.tasks.unshift(result.data);
                    else state.tasks[taskIndex] = result.data;
                    await put('tasks', result.data);
                } else {
                    await api(`/projects/${entry.projectId}/tasks/${taskId}`, 'DELETE');
                    state.tasks = state.tasks.filter(task => String(task.id) !== String(taskId));
                    await remove('tasks', taskId);
                }
                await remove('queue', entry.queueId);
            } catch (error) {
                if (!navigator.onLine) break;
                setSyncStatus('Sync needs attention', 'offline');
                notify(`Could not sync a change: ${error.message}`);
                break;
            }
        }
        if ((await all('queue')).length === 0) setSyncStatus('All changes saved');
        render();
    }

    async function saveTask(event) {
        event.preventDefault();
        const data = new FormData(els.task_form);
        const id = data.get('task_id');
        const projectId = Number(data.get('project_id'));
        const body = {
            title: data.get('title').trim(),
            notes: data.get('notes').trim() || null,
            due_date: data.get('due_date') ? new Date(data.get('due_date')).toISOString() : null,
            priority: data.get('priority'),
            done: data.has('done'),
        };
        const existing = state.tasks.find(task => String(task.id) === String(id));
        const previousSubtasks = existing?.subtasks || [];
        body.subtasks = String(data.get('subtasks') || '')
            .split(/\r?\n/)
            .map(title => title.trim())
            .filter(Boolean)
            .slice(0, 50)
            .map(title => ({
                title,
                done: previousSubtasks.find(subtask => subtask.title === title)?.done || false,
            }));
        const previousProjectId = existing?.project_id || projectId;
        try {
            if (!navigator.onLine || String(id).startsWith('local-')) {
                if (id && Number(previousProjectId) !== projectId) {
                    notify('Move tasks between projects while online.');
                    return;
                }
                await enqueue(projectId, id ? 'PATCH' : 'POST', id, body);
            } else if (id) {
                await api(`/projects/${previousProjectId}/tasks/${id}`, 'PATCH', { ...body, project_id: projectId });
                await cacheRemote();
            } else {
                await api(`/projects/${projectId}/tasks`, 'POST', body);
                await cacheRemote();
            }
            els.task_modal.classList.add('hidden');
            els.task_form.reset();
            notify(navigator.onLine ? 'Task saved' : 'Saved on this device. It will sync when you’re back online.');
            scheduleReminders();
        } catch (error) { notify(error.message); }
    }

    function openTask(task = null) {
        if (!state.projects.length) {
            notify('Create a project first to add a task.');
            openProject();
            return;
        }
        const form = els.task_form;
        form.reset();
        form.elements.task_id.value = task?.id || '';
        form.elements.title.value = task?.title || '';
        form.elements.notes.value = task?.notes || '';
        form.elements.subtasks.value = (task?.subtasks || []).map(subtask => subtask.title).join('\n');
        form.elements.project_id.value = String(task?.project_id || state.projectId || state.projects[0].id);
        form.elements.due_date.value = localDate(task?.due_date);
        form.elements.priority.value = task?.priority || 'medium';
        form.elements.done.checked = Boolean(task?.done);
        document.getElementById('task-modal-title').textContent = task ? 'Edit task' : 'Create a task';
        document.getElementById('delete-task').classList.toggle('hidden', !task);
        els.task_modal.classList.remove('hidden');
        form.elements.title.focus();
    }

    async function openProject(project = null) {
        els.project_form.reset();
        els.project_form.elements.project_id.value = project?.id || '';
        els.project_form.elements.name.value = project?.name || '';
        els.project_form.elements.color.value = project?.color || '#8977f8';
        els.project_form.elements.description.value = project?.description || '';
        els.project_form.elements.icon.value = project?.icon || 'icon1.png';
        document.querySelectorAll('[data-project-icon]').forEach(button => {
            const isSelected = button.dataset.projectIcon === els.project_form.elements.icon.value;
            button.classList.toggle('is-selected', isSelected);
            button.setAttribute('aria-pressed', String(isSelected));
        });
        document.getElementById('project-modal-title').textContent = project ? 'Edit project' : 'New project';
        document.getElementById('delete-project').classList.toggle('hidden', !project);
        els.project_modal.classList.remove('hidden');
        els.project_form.elements.name.focus();
    }

    function toggleTheme() {
        document.body.classList.toggle('dark');
        localStorage.setItem('taskflow-theme', document.body.classList.contains('dark') ? 'dark' : 'light');
        updateThemeButton();
    }

    function updateThemeButton() {
        const isDark = document.body.classList.contains('dark');
        const themeButton = document.getElementById('theme-button');
        updateThemeToggle(themeButton, isDark);
    }

    function scheduleReminders() {
        if (!('Notification' in window) || Notification.permission !== 'granted') return;
        state.tasks.filter(task => !task.done && task.due_date).forEach(task => {
            const dueTime = new Date(task.due_date).getTime();
            const fireAt = dueTime - 60 * 60 * 1000;
            const key = `taskflow-reminded-${task.id}-${dueTime}`;
            const wait = fireAt - Date.now();
            if (wait > 0 && wait < 2_147_000_000 && !localStorage.getItem(key)) {
                setTimeout(() => {
                    if (Notification.permission === 'granted' && !localStorage.getItem(key)) {
                        new Notification('Task due in one hour', { body: task.title, icon: '/favicon.ico', tag: key });
                        localStorage.setItem(key, '1');
                    }
                }, wait);
            }
        });
    }

    document.querySelectorAll('.main-nav [data-filter]').forEach(link => link.addEventListener('click', () => {
        document.getElementById('sidebar').classList.remove('open');
    }));
    els.project_nav.addEventListener('click', event => {
        const edit = event.target.closest('[data-edit-project]');
        if (edit) {
            openProject(state.projects.find(project => String(project.id) === edit.dataset.editProject));
            return;
        }
        const button = event.target.closest('[data-project]');
        if (!button) return;
        document.getElementById('sidebar').classList.remove('open');
    });
    document.getElementById('new-task').addEventListener('click', () => openTask());
    document.getElementById('new-task-side').addEventListener('click', () => openTask());
    els.task_list.addEventListener('click', async event => {
        if (event.target.id === 'empty-add-task') return openTask();
        const subtaskCheck = event.target.closest('.subtask-check');
        if (subtaskCheck) {
            const row = subtaskCheck.closest('[data-task-row]');
            const task = state.tasks.find(item => String(item.id) === row.dataset.taskRow);
            if (!task) return;
            const subtasks = [...(task.subtasks || [])];
            const index = Number(subtaskCheck.dataset.subtaskIndex);
            subtasks[index] = { ...subtasks[index], done: subtaskCheck.checked };
            try {
                if (!navigator.onLine || String(task.id).startsWith('local-')) {
                    await enqueue(task.project_id, 'PATCH', task.id, { subtasks });
                } else {
                    await api(`/projects/${task.project_id}/tasks/${task.id}`, 'PATCH', { subtasks });
                    await cacheRemote();
                }
            } catch (error) {
                render();
                notify(error.message);
            }
            return;
        }
        const check = event.target.closest('.task-check');
        if (check) {
            const row = check.closest('[data-task-row]');
            const task = state.tasks.find(item => String(item.id) === row.dataset.taskRow);
            if (!task) return;
            const body = { done: check.checked };
            try {
                if (!navigator.onLine || String(task.id).startsWith('local-')) await enqueue(task.project_id, 'PATCH', task.id, body);
                else {
                    await api(`/projects/${task.project_id}/tasks/${task.id}`, 'PATCH', body);
                    await cacheRemote();
                }
            } catch (error) { check.checked = !check.checked; notify(error.message); }
            return;
        }
        const edit = event.target.closest('[data-edit-task]');
        if (edit) openTask(state.tasks.find(task => String(task.id) === edit.dataset.editTask));
    });
    els.task_form.addEventListener('submit', saveTask);
    document.getElementById('delete-task').addEventListener('click', async () => {
        const id = els.task_form.elements.task_id.value;
        const task = state.tasks.find(item => String(item.id) === String(id));
        if (!task || !confirm('Delete this task?')) return;
        try {
            if (!navigator.onLine || String(id).startsWith('local-')) await enqueue(task.project_id, 'DELETE', id);
            else {
                await api(`/projects/${task.project_id}/tasks/${id}`, 'DELETE');
                await cacheRemote();
            }
            els.task_modal.classList.add('hidden');
            notify('Task deleted');
        } catch (error) { notify(error.message); }
    });
    document.getElementById('add-project').addEventListener('click', () => openProject());
    document.getElementById('dashboard-add-project').addEventListener('click', () => openProject());
    document.querySelectorAll('[data-project-icon]').forEach(button => button.addEventListener('click', () => {
        els.project_form.elements.icon.value = button.dataset.projectIcon;
        document.querySelectorAll('[data-project-icon]').forEach(option => {
            const isSelected = option === button;
            option.classList.toggle('is-selected', isSelected);
            option.setAttribute('aria-pressed', String(isSelected));
        });
    }));
    els.workspace_project_grid.addEventListener('click', event => {
        if (event.target.closest('[data-dashboard-add-project]')) openProject();
    });
    els.project_form.addEventListener('submit', async event => {
        event.preventDefault();
        const form = new FormData(els.project_form);
        const id = form.get('project_id');
        const body = {
            name: form.get('name').trim(),
            color: form.get('color'),
            description: form.get('description').trim() || null,
            icon: form.get('icon'),
        };
        try {
            if (!navigator.onLine) throw new Error('Projects can only be changed while online.');
            if (id) await api(`/projects/${id}`, 'PATCH', body);
            else await api('/projects', 'POST', body);
            els.project_modal.classList.add('hidden');
            await cacheRemote();
            notify(id ? 'Project updated' : 'Project created');
        } catch (error) { notify(error.message); }
    });
    document.getElementById('delete-project').addEventListener('click', async () => {
        const id = els.project_form.elements.project_id.value;
        if (!id || !confirm('Delete this project and all its tasks?')) return;
        try {
            if (!navigator.onLine) throw new Error('Projects can only be changed while online.');
            await api(`/projects/${id}`, 'DELETE');
            if (String(state.projectId) === String(id)) state.projectId = null;
            els.project_modal.classList.add('hidden');
            await cacheRemote();
            notify('Project deleted');
        } catch (error) { notify(error.message); }
    });
    document.querySelectorAll('.close-modal').forEach(button => button.addEventListener('click', () => button.closest('.modal-backdrop').classList.add('hidden')));
    document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.addEventListener('click', event => {
        if (event.target === backdrop) backdrop.classList.add('hidden');
    }));
    document.getElementById('theme-button').addEventListener('click', toggleTheme);
    document.getElementById('sort-button').addEventListener('click', () => { state.sortAsc = !state.sortAsc; render(); });
    document.getElementById('menu-toggle').addEventListener('click', () => document.getElementById('sidebar').classList.toggle('open'));
    window.addEventListener('online', () => { setSyncStatus('Back online', 'syncing'); cacheRemote(); });
    window.addEventListener('offline', () => setSyncStatus('Working offline', 'offline'));
    window.addEventListener('keydown', event => {
        if (event.key === 'Escape') document.querySelectorAll('.modal-backdrop').forEach(modal => modal.classList.add('hidden'));
        if (event.key.toLowerCase() === 'n' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) openTask();
    });

    document.body.classList.toggle('dark', localStorage.getItem('taskflow-theme') === 'dark');
    updateThemeButton();
    render();
    cacheRemote();
    scheduleReminders();
    if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(error => console.error('Service worker registration failed:', error));
}

if (page.classList.contains('settings-page')) {
    initializeAccountMenu();
    let toastTimer;

    function notify(message) {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('show'), 2600);
    }

    const themeButtons = [...document.querySelectorAll('[data-theme-toggle]')];
    const themePreferenceButton = document.getElementById('settings-theme');
    document.getElementById('settings-menu-toggle').addEventListener('click', () => {
        document.querySelector('.settings-page .sidebar').classList.toggle('open');
    });
    const syncThemeButton = () => {
        const isDark = page.classList.contains('dark');
        themeButtons.forEach(button => updateThemeToggle(button, isDark));
        if (themePreferenceButton) {
            themePreferenceButton.textContent = isDark ? 'Light mode' : 'Dark mode';
            themePreferenceButton.setAttribute('aria-pressed', String(isDark));
        }
    };
    page.classList.toggle('dark', localStorage.getItem('taskflow-theme') === 'dark');
    syncThemeButton();
    [...themeButtons, themePreferenceButton].filter(Boolean).forEach(themeButton => themeButton.addEventListener('click', () => {
        page.classList.toggle('dark');
        localStorage.setItem('taskflow-theme', page.classList.contains('dark') ? 'dark' : 'light');
        syncThemeButton();
    }));

    const reminderButton = document.getElementById('enable-reminders');
    if (reminderButton && 'Notification' in window && Notification.permission === 'granted') {
        reminderButton.textContent = 'Enabled';
        reminderButton.disabled = true;
    }
    reminderButton?.addEventListener('click', async () => {
        if (!('Notification' in window)) {
            notify('Notifications are not supported by this browser.');
            return;
        }
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            notify('Notifications were not enabled.');
            return;
        }
        reminderButton.textContent = 'Enabled';
        reminderButton.disabled = true;
        notify('One-hour reminders are enabled on this device.');
    });

    initializeProfileSettings(notify, user => {
        document.getElementById('user-name').textContent = user.name;
        document.getElementById('user-email').textContent = user.email;
        document.getElementById('user-avatar-initial').textContent = (user.name.trim() || 'A').charAt(0).toUpperCase();
        const avatarImage = document.getElementById('user-avatar-image');
        avatarImage.referrerPolicy = 'no-referrer';
        if (user.avatar_url) {
            avatarImage.src = user.avatar_url;
            avatarImage.classList.remove('hidden');
            document.getElementById('user-avatar-initial').classList.add('hidden');
        } else {
            avatarImage.removeAttribute('src');
            avatarImage.classList.add('hidden');
            document.getElementById('user-avatar-initial').classList.remove('hidden');
        }
    });
}
