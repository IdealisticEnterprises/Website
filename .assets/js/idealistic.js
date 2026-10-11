(function () {
    'use strict';

    const config = window.IO_CONFIG || {};
    const i18n = window.IO_I18N || {};
    config.lastId = parseInt(config.lastId, 10) || 0;
    config.state = config.state || '';
    config.pollingState = false;

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

    document.addEventListener('DOMContentLoaded', function () {

        const ui = {
            box: document.getElementById('chat-box'),
            form: document.getElementById('chat-form'),
            input: document.getElementById('message'),
            btn: document.getElementById('send-btn'),
            micBtn: document.getElementById('mic-btn'),
            toastEl: document.getElementById('errorToast'),
            toastMsg: document.getElementById('toastMessage'),
            fileInput: document.getElementById('file-input'),
            fileBtn: document.getElementById('file-btn'),
            deleteBtn: document.getElementById('delete-btn'),
            signoutBtn: document.getElementById('signout-btn'),
            signinBtn: document.getElementById('signin-btn'),
            previewDiv: document.getElementById('upload-preview')
        };

        if (!ui.box || !ui.form || !ui.input || !ui.btn) {
            return;
        }

        const state = {
            selectedFiles: [], voiceBlob: null, isRecording: false, mediaRecorder: null,
            audioChunks: [], isThinking: false,
            pendingBubble: null, timedOutMessageDate: null, pendingUserDate: null
        };

        const errorToast = window.bootstrap ? new bootstrap.Toast(ui.toastEl, {delay: 4000}) : null;

        if (config.forceLogin && window.bootstrap) {
            const forceLoginModalEl = document.getElementById('signInModal');
            if (forceLoginModalEl) {
                new bootstrap.Modal(forceLoginModalEl).show();
            }
        }

        let pollTimer = null;
        let thinkingTimer = null;

        const showError = (msg) => {
            if (!ui.toastEl || !ui.toastMsg) return;
            ui.toastMsg.textContent = msg;
            if (errorToast) errorToast.show();
        };

        const parseNaiveDate = (dateStr) => {
            if (!dateStr) return null;
            const iso = dateStr.includes('T') ? dateStr : dateStr.replace(' ', 'T') + 'Z';
            const d = new Date(iso);
            return isNaN(d.getTime()) ? null : d;
        };

        const serverTimeAtLoad = parseNaiveDate(config.serverTime);
        const clientTimeAtLoad = Date.now();

        const msSinceServerDate = (dateStr) => {
            const messageTime = parseNaiveDate(dateStr);
            if (!messageTime || !serverTimeAtLoad) return 0;
            const elapsedAtLoad = serverTimeAtLoad.getTime() - messageTime.getTime();
            const elapsedSinceLoad = Date.now() - clientTimeAtLoad;
            return elapsedAtLoad + elapsedSinceLoad;
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                } else if (entry.boundingClientRect.top > 0) {
                    entry.target.classList.remove('is-visible');
                }
            });
        }, {threshold: 0.05, rootMargin: "0px 0px -50px 0px"});
        document.querySelectorAll('.reveal-on-scroll').forEach(el => observer.observe(el));

        document.addEventListener('focusin', function (e) {
            if (window.innerWidth <= 991 && e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') && e.target.id !== 'message') {
                const gb = document.querySelector('.glass-bottom');
                if (gb) gb.style.display = 'none';
            }
        });
        document.addEventListener('focusout', function (e) {
            if (window.innerWidth <= 991 && e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') && e.target.id !== 'message') {
                const gb = document.querySelector('.glass-bottom');
                if (gb) gb.style.display = '';
            }
        });

        const featureBoxes = document.querySelectorAll('.feature-box-inner');
        featureBoxes.forEach(box => {
            box.addEventListener('click', function () {
                const featureWrapper = document.querySelector('.features-grid-wrapper');
                const wasActive = this.classList.contains('active-feature');
                featureBoxes.forEach(b => b.classList.remove('active-feature'));
                if (wasActive) {
                    featureWrapper.classList.remove('has-active');
                } else {
                    this.classList.add('active-feature');
                    featureWrapper.classList.add('has-active');
                }
            });
        });
        document.addEventListener('click', function (e) {
            const featureWrapper = document.querySelector('.features-grid-wrapper');
            const clickedFeature = e.target.closest('.feature-box-inner');
            if (!clickedFeature && featureWrapper && featureWrapper.classList.contains('has-active')) {
                document.querySelectorAll('.feature-box-inner').forEach(b => b.classList.remove('active-feature'));
                featureWrapper.classList.remove('has-active');
            }
        });

        window.launchDemo = function () {
            const urlField = document.getElementById('demoUrlInput');
            let urlInput = urlField ? urlField.value.trim() : '';
            if (urlInput === '') urlInput = 'https://example.com';
            if (!urlInput.startsWith('http://') && !urlInput.startsWith('https://')) urlInput = 'https://' + urlInput;
            window.open('https://idealistic.ai/io/v1/portal/tryWidget/?url=' + encodeURIComponent(urlInput), '_blank');
        };
        const demoInputTarget = document.getElementById('demoUrlInput');
        if (demoInputTarget) {
            demoInputTarget.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    window.launchDemo();
                }
            });
        }

        document.querySelectorAll('.embed-snippet-copy-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const targetId = this.dataset.copyTarget;
                const codeEl = targetId ? document.getElementById(targetId) : null;
                const text = codeEl ? codeEl.textContent : '';
                navigator.clipboard.writeText(text).then(() => {
                    const original = this.innerHTML;
                    this.innerHTML = '<i class="bi bi-check2"></i>';
                    setTimeout(() => {
                        this.innerHTML = original;
                    }, 2000);
                });
            });
        });

        function initCarousel(scrollId, dotsId, originalCount) {
            const scrollEl = document.getElementById(scrollId);
            const dotsEl = document.getElementById(dotsId);
            if (!scrollEl || !dotsEl || !originalCount) return;

            const cards = scrollEl.children;
            for (let i = originalCount; i < cards.length; i++) {
                cards[i].setAttribute('aria-hidden', 'true');
                cards[i].setAttribute('inert', '');
            }
            for (let i = 0; i < originalCount; i++) {
                const dot = document.createElement('div');
                dot.className = 'review-dot' + (i === 0 ? ' active' : '');
                dot.onclick = () => {
                    if (cards.length > 0) {
                        const itemWidth = cards[0].getBoundingClientRect().width;
                        const gap = parseFloat(window.getComputedStyle(scrollEl).gap) || 0;
                        scrollEl.scrollTo({left: i * (itemWidth + gap), behavior: 'smooth'});
                    }
                };
                dotsEl.appendChild(dot);
            }

            let isHovered = false, isDown = false, startX, startScrollLeft;

            const updateActiveDot = () => {
                if (cards.length > 0) {
                    const itemWidth = cards[0].getBoundingClientRect().width;
                    const gap = parseFloat(window.getComputedStyle(scrollEl).gap) || 0;
                    const currentIndex = Math.round(scrollEl.scrollLeft / (itemWidth + gap)) % originalCount;
                    if (!isNaN(currentIndex)) {
                        dotsEl.querySelectorAll('.review-dot').forEach((d, i) => d.classList.toggle('active', i === currentIndex));
                    }
                }
            };
            scrollEl.addEventListener('scroll', updateActiveDot, {passive: true});

            const scrollContent = () => {
                if (!isHovered && !isDown) {
                    scrollEl.scrollLeft += 0.8;
                    if (scrollEl.scrollLeft >= scrollEl.scrollWidth / 2) scrollEl.scrollLeft = 0;
                }
                requestAnimationFrame(scrollContent);
            };
            requestAnimationFrame(scrollContent);

            scrollEl.addEventListener('mouseenter', () => isHovered = true);
            scrollEl.addEventListener('mouseleave', () => isHovered = false);
            scrollEl.addEventListener('touchstart', () => isHovered = true, {passive: true});
            scrollEl.addEventListener('touchend', () => setTimeout(() => isHovered = false, 1500));

            scrollEl.addEventListener('mousedown', (e) => {
                isDown = true;
                startX = e.pageX - scrollEl.offsetLeft;
                startScrollLeft = scrollEl.scrollLeft;
                scrollEl.style.cursor = 'grabbing';
            });
            scrollEl.addEventListener('mouseleave', () => {
                isDown = false;
                scrollEl.style.cursor = 'grab';
            });
            scrollEl.addEventListener('mouseup', () => {
                isDown = false;
                scrollEl.style.cursor = 'grab';
            });
            scrollEl.addEventListener('mousemove', (e) => {
                if (!isDown) return;
                e.preventDefault();
                const x = e.pageX - scrollEl.offsetLeft;
                const walk = (x - startX) * 1.5;
                scrollEl.scrollLeft = startScrollLeft - walk;
            });
        }

        const iconsScroll = document.getElementById('icons-scroll-area');
        if (iconsScroll) initCarousel('icons-scroll-area', 'icons-dots', parseInt(iconsScroll.dataset.originalCount || '0', 10));

        const animWords = [
            i18n.anim_widget, i18n.anim_chatbot, i18n.anim_chatbox, i18n.anim_support,
            i18n.anim_clients, i18n.anim_business, i18n.anim_modern
        ].filter(Boolean);

        if (animWords.length > 0) {
            let animIndex = 0;
            const dynamicWordEls = document.querySelectorAll('.mock-dynamic-word');
            dynamicWordEls.forEach(el => {
                el.textContent = animWords[0];
            });
            setInterval(() => {
                dynamicWordEls.forEach(el => el.classList.add('is-fading'));
                setTimeout(() => {
                    animIndex = (animIndex + 1) % animWords.length;
                    dynamicWordEls.forEach(el => {
                        el.textContent = animWords[animIndex];
                        el.classList.remove('is-fading');
                    });
                }, 350);
            }, 2500);
        }

        const measureCtx = document.createElement('canvas').getContext('2d');

        function getPhWidth(text, el) {
            measureCtx.font = window.getComputedStyle(el).font;
            return measureCtx.measureText(text).width;
        }

        setInterval(() => {
            document.querySelectorAll('textarea#message').forEach(el => {
                if (!el.dataset.origPh) {
                    el.dataset.origPh = el.placeholder;
                    el.dataset.phSpaced = el.placeholder + "    \u2022    ";
                }
                if (document.activeElement === el) {
                    if (el.placeholder !== el.dataset.origPh) el.placeholder = el.dataset.origPh;
                    return;
                }
                const textWidth = getPhWidth(el.dataset.origPh, el);
                if (textWidth > el.clientWidth - 20) {
                    let p = el.dataset.phSpaced;
                    el.dataset.phSpaced = p.substring(1) + p[0];
                    el.placeholder = el.dataset.phSpaced;
                } else if (el.placeholder !== el.dataset.origPh) {
                    el.placeholder = el.dataset.origPh;
                }
            });
        }, 100);

        const setPlaceholder = (text) => {
            ui.input.placeholder = text;
            ui.input.dataset.origPh = text;
            ui.input.dataset.phSpaced = text + "    \u2022    ";
        };

        const updateInputButtons = () => {
            const hasText = ui.input.value.trim().length > 0;
            const hasAttachment = state.selectedFiles.length > 0 || !!state.voiceBlob;
            const busy = state.isThinking || hasAttachment;
            ui.btn.classList.toggle('is-hidden', !(hasText || busy));
            if (ui.micBtn) {
                ui.micBtn.classList.toggle('is-hidden', hasText || busy);
            }
        };

        const toggleUtilityButtons = (hide) => {
            [ui.deleteBtn, ui.signoutBtn, ui.signinBtn].forEach(btn => {
                if (!btn) return;
                btn.classList.toggle('is-hidden', hide);
            });
        };

        const showThinking = (forceScroll, timeoutMs) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'd-flex w-100 thinking-wrapper anim-slide-up mb-3';
            wrapper.innerHTML = '<div class="msg-bubble msg-assistant d-flex flex-column justify-content-center" style="width: 260px; height: 72px; gap: 10px;">'
                + '<div class="skeleton-block" style="width: 100%; height: 14px; border-radius: 0;"></div>'
                + '<div class="skeleton-block" style="width: 65%; height: 14px; border-radius: 0;"></div></div>';
            ui.box.appendChild(wrapper);
            smoothScrollToBottom(forceScroll);
            state.isThinking = true;
            ui.input.disabled = true;
            ui.btn.disabled = true;
            setPlaceholder(i18n.chat_wait_reply || 'Wait for the reply...');
            updateInputButtons();
            clearTimeout(thinkingTimer);
            const effectiveTimeout = timeoutMs ?? ((config.promptTimeoutSeconds || 60) * 1000);
            thinkingTimer = setTimeout(removeThinking, Math.max(0, effectiveTimeout));
        };

        const removeThinking = () => {
            const el = document.querySelector('.thinking-wrapper');
            if (el) el.remove();
            clearTimeout(thinkingTimer);
            state.isThinking = false;
            ui.input.disabled = false;
            ui.btn.disabled = false;
            setPlaceholder(config.userTypePlaceholder || i18n.chat_message_ph || '');
            updateInputButtons();
            ui.input.focus();
        };
        config.userTypePlaceholder = i18n.chat_message_ph || ui.input.placeholder;
        setPlaceholder(config.userTypePlaceholder);

        function smoothScrollToBottom(force) {
            setTimeout(() => {
                const isAtBottom = ui.box.scrollHeight - ui.box.scrollTop - ui.box.clientHeight <= 150;
                if (force || isAtBottom) ui.box.scrollTo({top: ui.box.scrollHeight, behavior: 'smooth'});
            }, 10);
        }

        ui.input.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
            if (this.value === '') this.style.height = 'auto';
            const hasText = this.value.trim().length > 0;
            toggleUtilityButtons(hasText);
            updateInputButtons();
        });

        ui.input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (!ui.btn.disabled) ui.btn.click();
            }
        });

        window.clearFile = () => {
            state.selectedFiles = [];
            if (ui.fileInput) ui.fileInput.value = '';
            renderUIState();
        };
        window.clearVoice = () => {
            state.voiceBlob = null;
            renderUIState();
        };

        const renderUIState = () => {
            ui.previewDiv.innerHTML = '';
            const hasText = ui.input.value.trim().length > 0;

            if (state.voiceBlob) {
                ui.input.disabled = true;
                setPlaceholder(i18n.chat_voice_ready || 'Voice message ready to send...');
                ui.fileBtn.disabled = true;
                toggleUtilityButtons(true);
                const audioUrl = URL.createObjectURL(state.voiceBlob);
                ui.previewDiv.innerHTML = '<div class="d-flex align-items-center bg-danger rounded-pill p-1 pe-3 mb-1">'
                    + '<audio src="' + audioUrl + '" controls class="me-2 rounded-pill" style="height: 35px; max-width: 200px;"></audio>'
                    + '<i class="bi bi-x-square-fill text-white fs-5" style="cursor:pointer" onclick="clearVoice()"></i></div>';
            } else if (state.selectedFiles.length > 0) {
                ui.input.disabled = false;
                setPlaceholder(config.userTypePlaceholder);
                toggleUtilityButtons(true);
                ui.previewDiv.innerHTML = '<span class="badge bg-secondary text-white p-2 fs-6 mb-1 me-1"><i class="bi bi-file-earmark"></i> '
                    + escapeHtml(state.selectedFiles[0].name) + ' <i class="bi bi-x-square ms-2" style="cursor:pointer" onclick="clearFile()"></i></span>';
            } else {
                if (!state.isThinking) {
                    ui.input.disabled = false;
                    setPlaceholder(config.userTypePlaceholder);
                }
                ui.fileBtn.disabled = false;
                toggleUtilityButtons(hasText);
            }
            updateInputButtons();
            ui.input.style.height = 'auto';
        };

        if (ui.fileBtn) ui.fileBtn.onclick = () => ui.fileInput && ui.fileInput.click();
        if (ui.fileInput) {
            ui.fileInput.onchange = (e) => {
                if (e.target.files.length > 0) {
                    state.selectedFiles = [e.target.files[0]];
                    state.voiceBlob = null;
                    renderUIState();
                }
                ui.fileInput.value = '';
            };
        }

        if (ui.micBtn) {
            const handleVoiceToggle = async (e) => {
                if (e.type !== 'touchstart') e.preventDefault();
                if (ui.input.value.trim().length > 0 || state.selectedFiles.length > 0 || state.isThinking) return;

                if (!state.isRecording) {
                    try {
                        const stream = await navigator.mediaDevices.getUserMedia({audio: true});
                        state.mediaRecorder = new MediaRecorder(stream);
                        state.audioChunks = [];
                        state.mediaRecorder.ondataavailable = ev => {
                            if (ev.data.size > 0) state.audioChunks.push(ev.data);
                        };
                        state.mediaRecorder.onstop = () => {
                            const type = state.mediaRecorder.mimeType || 'audio/ogg';
                            state.voiceBlob = new Blob(state.audioChunks, {type: type});
                            state.selectedFiles = [];
                            ui.input.value = '';
                            stream.getTracks().forEach(track => track.stop());
                            ui.micBtn.classList.remove('is-recording');
                            ui.micBtn.innerHTML = '<i class="bi bi-mic-fill"></i>';
                            renderUIState();
                        };
                        state.mediaRecorder.start();
                        state.isRecording = true;
                        ui.micBtn.classList.add('is-recording');
                        ui.micBtn.innerHTML = '<i class="bi bi-stop-fill"></i>';
                        ui.input.disabled = true;
                        setPlaceholder(i18n.chat_recording || 'Recording... tap to stop');
                        ui.fileBtn.disabled = true;
                    } catch (err) {
                        showError(i18n.chat_microphone_error || 'Microphone access denied or unavailable.');
                    }
                } else {
                    state.mediaRecorder.stop();
                    state.isRecording = false;
                }
            };
            ui.micBtn.addEventListener('mousedown', handleVoiceToggle);
            ui.micBtn.addEventListener('touchstart', handleVoiceToggle, {passive: true});
        }

        const parseText = (text) => {
            let safeText = escapeHtml(text);
            safeText = safeText.replace(/^### +(.*)$/gm, '<strong class="msg-h3">$1</strong>');
            safeText = safeText.replace(/^## +(.*)$/gm, '<strong class="msg-h2">$1</strong>');
            safeText = safeText.replace(/^# +(.*)$/gm, '<strong class="msg-h1">$1</strong>');
            safeText = safeText.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            safeText = safeText.replace(/\*(.*?)\*/g, '<em>$1</em>');
            safeText = safeText.replace(/__(.*?)__/g, '<u>$1</u>');
            const urlRegex = /(https?:\/\/[^\s<>"'*]+[^<>"'*.,:;)\]\s])/g;
            safeText = safeText.replace(urlRegex, '<a href="$1" target="_blank" rel="noopener noreferrer" class="text-info text-decoration-underline" style="word-break: break-all;">$1</a>');
            return safeText;
        };

        const bubbleId = (sender, id) => 'msg-' + sender + '-' + id;

        const appendMsg = (dataObj, sender, forceScroll) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'd-flex w-100 anim-slide-up mb-3';
            const msg = document.createElement('div');
            msg.className = 'msg-bubble msg-' + sender + ' d-flex flex-column';
            if (dataObj.id) msg.id = bubbleId(sender, dataObj.id);
            if (dataObj.text) {
                const textNode = document.createElement('div');
                textNode.innerHTML = parseText(dataObj.text);
                msg.appendChild(textNode);
            }
            if (dataObj.attachment) {
                const att = dataObj.attachment;
                const attBox = document.createElement('div');
                attBox.className = 'attachment-box';
                let html = '<strong><i class="bi bi-paperclip"></i> ' + escapeHtml(att.name || i18n.chat_file || 'File') + '</strong>';
                if (att.analyzed_description) html += '<div class="mt-2 fst-italic">"' + escapeHtml(att.analyzed_description) + '"</div>';
                attBox.innerHTML = html;
                msg.appendChild(attBox);
            }
            wrapper.appendChild(msg);
            const thinking = document.querySelector('.thinking-wrapper');
            if (thinking) ui.box.insertBefore(wrapper, thinking); else ui.box.appendChild(wrapper);
            smoothScrollToBottom(forceScroll);
            return msg;
        };

        const request = async (url, fd) => {
            const res = await fetch(url, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: {'X-Requested-With': config.pollHeader}
            });
            if (!res.ok) throw new Error("HTTP " + res.status);
            return res.json();
        };

        const textNodeOf = (bubble) => Array.from(bubble.children).find(el => !el.classList.contains('attachment-box'));

        const patchText = (bubble, source) => {
            const incoming = textNodeOf(source);
            if (!incoming) return;
            let node = textNodeOf(bubble);
            if (!node) {
                node = document.createElement('div');
                bubble.insertBefore(node, bubble.firstChild);
            }
            if (node.innerHTML !== incoming.innerHTML) node.innerHTML = incoming.innerHTML;
        };

        const applyFragment = (root) => {
            let addedMessages = false;
            let gotAssistant = false;

            Array.from(root.children).forEach(wrapper => {
                const incoming = wrapper.firstElementChild;
                if (!incoming) return;
                const isUser = incoming.classList.contains('msg-user');
                const existing = incoming.id ? document.getElementById(incoming.id) : null;

                if (existing) {
                    patchText(existing, incoming);
                    return;
                }
                addedMessages = true;

                if (isUser && state.pendingBubble) {
                    const bubble = state.pendingBubble;
                    state.pendingBubble = null;
                    bubble.id = incoming.id;
                    patchText(bubble, incoming);
                    return;
                }
                const node = document.importNode(wrapper, true);
                const thinking = document.querySelector('.thinking-wrapper');
                if (thinking) ui.box.insertBefore(node, thinking); else ui.box.appendChild(node);
                if (!isUser) gotAssistant = true;
            });
            if (addedMessages) smoothScrollToBottom(false);
            return gotAssistant;
        };

        const evaluatePending = (gotAssistant, pendingDate) => {
            if (gotAssistant) {
                state.pendingUserDate = null;
                state.timedOutMessageDate = null;
                removeThinking();
            } else if (pendingDate) {
                state.pendingUserDate = pendingDate;
            }

            if (state.pendingUserDate) {
                const timeoutMs = (config.promptTimeoutSeconds || 60) * 1000;
                const elapsedMs = msSinceServerDate(state.pendingUserDate);

                if (elapsedMs >= timeoutMs) {
                    if (state.isThinking) removeThinking();
                    if (state.timedOutMessageDate !== state.pendingUserDate) {
                        state.timedOutMessageDate = state.pendingUserDate;
                        showError(i18n.chat_network_failed || 'The network request failed.');
                    }
                } else if (!state.isThinking) {
                    showThinking(false, timeoutMs - elapsedMs);
                }
            }
        };

        const sync = async () => {
            if (config.pollingState) return;
            config.pollingState = true;
            clearTimeout(pollTimer);
            try {
                const body = new URLSearchParams();
                body.append(config.pollField, '1');
                if (config.sessionId) body.append('session_id', config.sessionId);
                body.append('after_id', config.lastId > 15 ? config.lastId - 15 : -1);
                body.append('state', config.state || '');
                const response = await fetch(config.pollUrl, {
                    method: 'POST',
                    body: body,
                    credentials: 'same-origin',
                    headers: {'X-Requested-With': config.pollHeader}
                });
                let gotAssistant = false;
                let pendingDate = null;

                if (response.status !== 204) {
                    if (!response.ok) throw new Error("HTTP " + response.status);

                    if ((response.headers.get('Content-Type') || '').indexOf('application/json') !== -1) {
                        const res = await response.json();
                        if (res.success === false && res.message && res.message.length > 0) showError(res.message);
                        return;
                    }
                    const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
                    const root = doc.getElementById(config.fragmentId);
                    if (!root) return;
                    const language = root.dataset.language;

                    if (language && language !== config.language) {
                        window.location.reload();
                        return;
                    }
                    const incomingId = parseInt(root.dataset.lastId, 10);
                    if (incomingId > config.lastId) config.lastId = incomingId;
                    config.state = root.dataset.state || '';
                    pendingDate = root.dataset.pendingDate || null;
                    gotAssistant = applyFragment(root);
                }
                evaluatePending(gotAssistant, pendingDate);
            } catch (e) {
            } finally {
                config.pollingState = false;
                pollTimer = setTimeout(sync, state.isThinking ? 1200 : 4000);
            }
        };

        ui.form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (ui.input.value.trim().length === 0 && state.selectedFiles.length === 0 && !state.voiceBlob) return;
            if (state.isThinking || ui.btn.disabled) return;

            const msg = ui.input.value.trim();
            const is2FAAttempt = config.isDemo && !config.hasEmail && msg.length === config.codeLength;
            const optData = {};
            if (msg) optData.text = msg;
            if (state.selectedFiles.length > 0) optData.attachment = {
                name: state.selectedFiles[0].name,
                format: state.selectedFiles[0].type || 'File'
            };
            if (state.voiceBlob) optData.attachment = {
                name: i18n.chat_voice_message || 'Voice Message',
                format: i18n.chat_audio || 'Audio'
            };

            const optimisticBubble = appendMsg(optData, 'user', true);
            state.pendingBubble = optimisticBubble;

            ui.input.disabled = true;
            ui.btn.disabled = true;
            const originalBtnHtml = ui.btn.innerHTML;
            ui.btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

            const fd = new FormData();
            if (config.sessionId) fd.append('session_id', config.sessionId);
            fd.append('random_id', config.randomId);
            fd.append('language', config.language);
            if (!config.isDemo && config.defaultUrl) fd.append('default_url', config.defaultUrl);

            if (state.voiceBlob) {
                fd.append('voice', state.voiceBlob, 'voice_message.ogg');
            } else {
                if (msg) fd.append('message', msg);
                if (state.selectedFiles.length > 0) fd.append('file', state.selectedFiles[0]);
            }

            ui.input.value = '';
            window.clearFile();
            window.clearVoice();
            renderUIState();
            showThinking(true);
            sync();

            try {
                const res = await request(config.addUrl, fd);
                if (!res.success) {
                    state.pendingBubble = null;
                    showError(res.message);
                    removeThinking();
                    ui.btn.innerHTML = originalBtnHtml;
                } else {
                    ui.btn.innerHTML = originalBtnHtml;
                    if (is2FAAttempt) setTimeout(() => window.location.reload(), 5000);
                }
            } catch (err) {
                state.pendingBubble = null;
                showError(i18n.chat_network_failed || 'The network request failed.');
                removeThinking();
                ui.btn.innerHTML = originalBtnHtml;
            }
        });

        updateInputButtons();

        const loader = document.getElementById('loading-screen');
        if (loader) {
            loader.style.opacity = '0';
            setTimeout(() => loader.remove(), 500);
        }

        if (config.pendingDate) {
            evaluatePending(false, config.pendingDate);
        } else if (config.lastId > 0) {
            ui.input.focus();
        }
        pollTimer = setTimeout(sync, state.isThinking ? 1200 : 4000);

        if (window.IO_ACTION_TOAST && ui.toastEl && ui.toastMsg) {
            ui.toastEl.className = 'toast align-items-center border-0 text-bg-' + (window.IO_ACTION_TOAST.success ? 'success' : 'danger');
            ui.toastMsg.textContent = window.IO_ACTION_TOAST.message;
            if (window.bootstrap) new bootstrap.Toast(ui.toastEl).show();
            if (window.IO_ACTION_TOAST.success && window.history.replaceState) {
                const url = new URL(window.location);
                url.searchParams.delete('deleted');
                url.searchParams.delete('signed_out');
                url.searchParams.delete('signed_in');
                window.history.replaceState(null, '', url);
            }
        }
    });
})();