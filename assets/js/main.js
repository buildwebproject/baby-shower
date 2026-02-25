(function () {
    var body = document.body;
    if (!body) {
        return;
    }

    function showNotice(message) {
        window.alert(message);
    }

    function initShareButtons() {
        var whatsappBtn = document.getElementById('whatsappShareBtn');
        var copyBtn = document.getElementById('copyLinkBtn');
        var message = body.getAttribute('data-whatsapp-message') || 'આપ સૌનું હાર્દિક આમંત્રણ.';
        var headline = 'ભાવભર્યું આમંત્રણ';

        if (whatsappBtn) {
            whatsappBtn.addEventListener('click', function () {
                var shareText = headline + '\n' + message + '\n' + window.location.href;
                var waUrl = 'https://wa.me/?text=' + encodeURIComponent(shareText);
                window.open(waUrl, '_blank', 'noopener');
            });
        }

        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                var textToCopy = window.location.href;

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(textToCopy)
                        .then(function () {
                            showNotice('લિંક કૉપી થઈ ગઈ છે.');
                        })
                        .catch(function () {
                            showNotice('લિંક કૉપી કરવામાં મુશ્કેલી આવી.');
                        });
                    return;
                }

                var tempInput = document.createElement('input');
                tempInput.value = textToCopy;
                document.body.appendChild(tempInput);
                tempInput.select();
                try {
                    document.execCommand('copy');
                    showNotice('લિંક કૉપી થઈ ગઈ છે.');
                } catch (error) {
                    showNotice('લિંક કૉપી કરવામાં મુશ્કેલી આવી.');
                }
                document.body.removeChild(tempInput);
            });
        }
    }

    function initOpeningExperience() {
        var stage = document.getElementById('openingStage');
        var shell = document.getElementById('openingShell');
        var openBtn = document.getElementById('openCoverBtn');
        var statusNode = document.getElementById('openingStatus');
        var isRunning = false;
        var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var audioCtx = null;

        function setStatus(message) {
            if (statusNode) {
                statusNode.textContent = message;
            }
        }

        function playRibbonSound() {
            var AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) {
                return;
            }

            if (!audioCtx) {
                audioCtx = new AudioCtx();
            }
            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
            }

            var now = audioCtx.currentTime;
            var master = audioCtx.createGain();
            master.gain.setValueAtTime(0.0001, now);
            master.gain.exponentialRampToValueAtTime(0.24, now + 0.05);
            master.gain.exponentialRampToValueAtTime(0.0001, now + 0.92);
            master.connect(audioCtx.destination);

            [523.25, 659.25, 783.99].forEach(function (frequency, index) {
                var osc = audioCtx.createOscillator();
                var noteGain = audioCtx.createGain();
                var start = now + index * 0.08;
                var end = start + 0.42;

                osc.type = 'triangle';
                osc.frequency.setValueAtTime(frequency, start);
                osc.frequency.exponentialRampToValueAtTime(frequency * 1.015, end);

                noteGain.gain.setValueAtTime(0.0001, start);
                noteGain.gain.exponentialRampToValueAtTime(0.72, start + 0.04);
                noteGain.gain.exponentialRampToValueAtTime(0.0001, end);

                osc.connect(noteGain);
                noteGain.connect(master);
                osc.start(start);
                osc.stop(end + 0.02);
            });
        }

        if (!stage || !shell || !openBtn) {
            body.classList.add('gate-opened');
            return;
        }

        body.classList.remove('gate-opened');
        setStatus('કાર્ડ બંધ છે.');

        function openInvitationGate() {
            if (isRunning) {
                return;
            }
            isRunning = true;
            openBtn.disabled = true;

            setStatus('રિબન ખુલી રહી છે.');
            shell.classList.add('is-untying');

            try {
                playRibbonSound();
            } catch (error) {
                // Ignore sound failures and continue animation.
            }

            if (reduceMotion) {
                shell.classList.add('is-opened');
                body.classList.add('gate-opened');
                setStatus('આમંત્રણ ખુલ્લું છે.');
                return;
            }

            window.setTimeout(function () {
                shell.classList.add('is-opening');
                setStatus('દરવાજા ખુલી રહ્યા છે.');
            }, 760);

            window.setTimeout(function () {
                shell.classList.add('is-opened');
                body.classList.add('gate-opened');
                setStatus('આમંત્રણ ખુલ્લું છે.');
            }, 1980);
        }

        window.openInvitationGate = openInvitationGate;
        openBtn.addEventListener('click', openInvitationGate);
    }

    function initDownloadImage() {
        var downloadBtn = document.getElementById('downloadImageBtn');
        var invitationCard = document.getElementById('invitationCard');

        if (!downloadBtn || !invitationCard) {
            return;
        }

        downloadBtn.addEventListener('click', function () {
            if (typeof window.html2canvas !== 'function') {
                showNotice('Download feature માટે html2canvas ઉપલબ્ધ નથી.');
                return;
            }

            window.html2canvas(invitationCard, {
                scale: 2,
                useCORS: true,
                backgroundColor: null
            }).then(function (canvas) {
                var link = document.createElement('a');
                link.href = canvas.toDataURL('image/png');
                link.download = 'baby-shower-invitation.png';
                link.click();
            }).catch(function () {
                showNotice('ઇમેજ ડાઉનલોડ કરવામાં સમસ્યા આવી.');
            });
        });
    }

    // BABY PLAY WIDGET START
    function initBabyPlayWidget() {
        var widget = document.getElementById('babyPlayWidget');
        if (!widget) {
            return;
        }

        var avatarBtn = document.getElementById('babyPlayAvatar');
        var heartsHost = document.getElementById('babyPlayHearts');
        var tooltipNode = document.getElementById('babyPlayTooltip');
        var giggleAudio = document.getElementById('babyPlayAudio');
        var imageNode = widget.querySelector('.baby-play__avatar-img');

        if (!avatarBtn || !heartsHost) {
            return;
        }

        var cooldownMs = 840;
        var activeUntil = 0;
        var heartPool = ['💗', '💖', '💕', '💞'];
        var soundAvailable = !!giggleAudio;
        var maxHearts = 24;
        var fallbackAudioCtx = null;
        var tipTimerId = 0;
        var dragThreshold = 6;
        var viewportPadding = 8;
        var dragState = {
            active: false,
            moved: false,
            pointerId: null,
            startX: 0,
            startY: 0,
            startLeft: 0,
            startTop: 0,
            suppressClick: false
        };

        var customImage = (widget.getAttribute('data-baby-image') || '').trim();
        if (customImage !== '' && imageNode) {
            imageNode.src = customImage;
            widget.classList.add('has-custom-image');
            imageNode.addEventListener('error', function () {
                widget.classList.remove('has-custom-image');
            });
        }

        function spawnHearts() {
            var count = 4 + Math.floor(Math.random() * 3);

            for (var i = 0; i < count; i += 1) {
                if (heartsHost.children.length >= maxHearts) {
                    heartsHost.removeChild(heartsHost.firstElementChild);
                }

                var heart = document.createElement('span');
                heart.className = 'baby-play__heart';
                heart.textContent = heartPool[Math.floor(Math.random() * heartPool.length)];
                heart.style.setProperty('--heart-x', (Math.random() * 76 - 38).toFixed(2) + 'px');
                heart.style.setProperty('--heart-end-x', (Math.random() * 90 - 45).toFixed(2) + 'px');
                heart.style.setProperty('--heart-size', (0.82 + Math.random() * 0.48).toFixed(2));
                heart.style.setProperty('--heart-duration', (920 + Math.random() * 360).toFixed(0) + 'ms');
                heart.style.animationDelay = (i * 38) + 'ms';
                heartsHost.appendChild(heart);

                heart.addEventListener('animationend', function () {
                    if (this && this.parentNode) {
                        this.parentNode.removeChild(this);
                    }
                });
            }
        }

        if (giggleAudio) {
            giggleAudio.addEventListener('error', function () {
                soundAvailable = false;
            });
        }

        function clampNumber(value, min, max) {
            return Math.min(Math.max(value, min), max);
        }

        function showTooltip(durationMs) {
            if (!tooltipNode) {
                return;
            }

            widget.classList.add('show-tip');
            if (tipTimerId) {
                window.clearTimeout(tipTimerId);
            }
            if (durationMs && durationMs > 0) {
                tipTimerId = window.setTimeout(function () {
                    widget.classList.remove('show-tip');
                }, durationMs);
            }
        }

        function keepInsideViewport() {
            if (!widget.style.left || !widget.style.top) {
                return;
            }

            var rect = widget.getBoundingClientRect();
            var maxLeft = Math.max(viewportPadding, window.innerWidth - rect.width - viewportPadding);
            var maxTop = Math.max(viewportPadding, window.innerHeight - rect.height - viewportPadding);
            widget.style.left = clampNumber(rect.left, viewportPadding, maxLeft) + 'px';
            widget.style.top = clampNumber(rect.top, viewportPadding, maxTop) + 'px';
        }

        function startDrag(event) {
            if (event.button !== undefined && event.button !== 0) {
                return;
            }

            var rect = widget.getBoundingClientRect();
            dragState.active = true;
            dragState.moved = false;
            dragState.pointerId = event.pointerId;
            dragState.startX = event.clientX;
            dragState.startY = event.clientY;
            dragState.startLeft = rect.left;
            dragState.startTop = rect.top;

            widget.style.left = rect.left + 'px';
            widget.style.top = rect.top + 'px';
            widget.style.right = 'auto';
            widget.style.bottom = 'auto';
            widget.classList.add('is-dragging');

            if (avatarBtn.setPointerCapture && event.pointerId !== undefined) {
                try {
                    avatarBtn.setPointerCapture(event.pointerId);
                } catch (error) {
                    // Ignore pointer capture failures.
                }
            }

            showTooltip(1800);
        }

        function moveDrag(event) {
            if (!dragState.active) {
                return;
            }
            if (dragState.pointerId !== null && event.pointerId !== dragState.pointerId) {
                return;
            }

            var deltaX = event.clientX - dragState.startX;
            var deltaY = event.clientY - dragState.startY;

            if (!dragState.moved && (Math.abs(deltaX) > dragThreshold || Math.abs(deltaY) > dragThreshold)) {
                dragState.moved = true;
            }
            if (!dragState.moved) {
                return;
            }

            var rect = widget.getBoundingClientRect();
            var maxLeft = Math.max(viewportPadding, window.innerWidth - rect.width - viewportPadding);
            var maxTop = Math.max(viewportPadding, window.innerHeight - rect.height - viewportPadding);

            widget.style.left = clampNumber(dragState.startLeft + deltaX, viewportPadding, maxLeft) + 'px';
            widget.style.top = clampNumber(dragState.startTop + deltaY, viewportPadding, maxTop) + 'px';
            event.preventDefault();
        }

        function endDrag(event) {
            if (!dragState.active) {
                return;
            }
            if (event && dragState.pointerId !== null && event.pointerId !== undefined && event.pointerId !== dragState.pointerId) {
                return;
            }

            dragState.active = false;
            widget.classList.remove('is-dragging');

            if (dragState.moved) {
                dragState.suppressClick = true;
                window.setTimeout(function () {
                    dragState.suppressClick = false;
                }, 0);
            }

            if (avatarBtn.releasePointerCapture && dragState.pointerId !== null) {
                try {
                    avatarBtn.releasePointerCapture(dragState.pointerId);
                } catch (error) {
                    // Ignore pointer release failures.
                }
            }

            dragState.pointerId = null;
            keepInsideViewport();
        }

        function ensureFallbackAudioContext() {
            var AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) {
                return null;
            }

            if (!fallbackAudioCtx) {
                fallbackAudioCtx = new AudioCtx();
            }

            if (fallbackAudioCtx.state === 'suspended') {
                fallbackAudioCtx.resume();
            }

            return fallbackAudioCtx;
        }

        function playFallbackGiggleTone() {
            var ctx = ensureFallbackAudioContext();
            if (!ctx) {
                return;
            }

            var now = ctx.currentTime;
            var master = ctx.createGain();
            master.gain.setValueAtTime(0.0001, now);
            master.gain.exponentialRampToValueAtTime(0.18, now + 0.05);
            master.gain.exponentialRampToValueAtTime(0.0001, now + 0.62);
            master.connect(ctx.destination);

            [690, 760, 660, 820].forEach(function (frequency, index) {
                var osc = ctx.createOscillator();
                var noteGain = ctx.createGain();
                var start = now + index * 0.08;
                var end = start + 0.2;

                osc.type = 'sine';
                osc.frequency.setValueAtTime(frequency, start);
                osc.frequency.exponentialRampToValueAtTime(frequency * 1.03, end);

                noteGain.gain.setValueAtTime(0.0001, start);
                noteGain.gain.exponentialRampToValueAtTime(0.65, start + 0.04);
                noteGain.gain.exponentialRampToValueAtTime(0.0001, end);

                osc.connect(noteGain);
                noteGain.connect(master);
                osc.start(start);
                osc.stop(end + 0.02);
            });
        }

        function playGiggleSound() {
            if (!giggleAudio || !soundAvailable) {
                playFallbackGiggleTone();
                return;
            }

            try {
                giggleAudio.currentTime = 0;
                var playPromise = giggleAudio.play();
                if (playPromise && typeof playPromise.catch === 'function') {
                    playPromise.catch(function () {
                        soundAvailable = false;
                        playFallbackGiggleTone();
                    });
                }
            } catch (error) {
                soundAvailable = false;
                playFallbackGiggleTone();
            }
        }

        function triggerLaugh() {
            var now = Date.now();
            if (now < activeUntil) {
                return;
            }

            activeUntil = now + cooldownMs;
            widget.classList.add('is-laughing');
            spawnHearts();
            playGiggleSound();

            window.setTimeout(function () {
                widget.classList.remove('is-laughing');
            }, cooldownMs - 20);
        }

        avatarBtn.addEventListener('pointerdown', startDrag);
        avatarBtn.addEventListener('pointermove', moveDrag);
        avatarBtn.addEventListener('pointerup', endDrag);
        avatarBtn.addEventListener('pointercancel', endDrag);
        avatarBtn.addEventListener('lostpointercapture', function () {
            endDrag();
        });

        avatarBtn.addEventListener('click', function () {
            if (dragState.suppressClick) {
                return;
            }
            triggerLaugh();
            showTooltip(1400);
        });
        avatarBtn.addEventListener('pointerenter', function (event) {
            if (event && event.pointerType === 'mouse' && !dragState.active) {
                triggerLaugh();
                showTooltip(1400);
            }
        });
        avatarBtn.addEventListener('focus', function () {
            if (document.activeElement === avatarBtn) {
                triggerLaugh();
                showTooltip(1800);
            }
        });

        window.addEventListener('resize', keepInsideViewport);

        if (body.classList.contains('gate-opened')) {
            showTooltip(2800);
        } else if (typeof MutationObserver === 'function') {
            var gateObserver = new MutationObserver(function () {
                if (body.classList.contains('gate-opened')) {
                    showTooltip(2800);
                    gateObserver.disconnect();
                }
            });
            gateObserver.observe(body, {
                attributes: true,
                attributeFilter: ['class']
            });
        }
    }
    // BABY PLAY WIDGET END

    initOpeningExperience();

    if (body.getAttribute('data-page') === 'invite') {
        initShareButtons();
        initDownloadImage();
        initBabyPlayWidget();
    }
})();
