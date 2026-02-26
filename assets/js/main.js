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

    function initPhotoMemorySlider() {
        var slider = document.getElementById('photoMemorySlider');
        if (!slider) {
            return;
        }

        var slides = slider.querySelectorAll('.memory-slide');
        var dots = slider.querySelectorAll('.memory-slider-dot');
        var captionNode = document.getElementById('photoMemoryCaption');
        var intervalMs = parseInt(slider.getAttribute('data-interval-ms') || '5000', 10);
        var activeIndex = 0;
        var timerId = 0;

        if (!slides.length || slides.length < 2) {
            return;
        }
        if (isNaN(intervalMs) || intervalMs < 1500) {
            intervalMs = 5000;
        }

        function setActive(index) {
            slides.forEach(function (slide, slideIndex) {
                var isActive = slideIndex === index;
                slide.classList.toggle('is-active', isActive);
                slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
            });

            dots.forEach(function (dot, dotIndex) {
                dot.classList.toggle('is-active', dotIndex === index);
            });

            if (captionNode) {
                captionNode.textContent = slides[index].getAttribute('data-label') || '';
            }
            activeIndex = index;
        }

        function nextSlide() {
            setActive((activeIndex + 1) % slides.length);
        }

        function stop() {
            if (timerId) {
                window.clearInterval(timerId);
                timerId = 0;
            }
        }

        function start() {
            stop();
            timerId = window.setInterval(nextSlide, intervalMs);
        }

        slider.addEventListener('mouseenter', stop);
        slider.addEventListener('mouseleave', start);
        slider.addEventListener('focusin', stop);
        slider.addEventListener('focusout', start);
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stop();
            } else {
                start();
            }
        });

        setActive(0);
        start();
    }

    function initMiniBalloonInteraction() {
        var host = document.getElementById('miniBalloons');
        if (!host) {
            return;
        }

        var balloons = host.querySelectorAll('.mini-balloon');
        var messageTimerId = 0;
        var confettiColors = ['#ff7fb8', '#ffa4ce', '#f26ca7', '#ffd166', '#8ecdf9', '#b690ff'];

        function showMessage() {
            host.classList.add('show-msg');
            if (messageTimerId) {
                window.clearTimeout(messageTimerId);
            }
            messageTimerId = window.setTimeout(function () {
                host.classList.remove('show-msg');
            }, 1800);
        }

        function burstConfetti(balloon) {
            var rect = balloon.getBoundingClientRect();
            var hostRect = host.getBoundingClientRect();
            var baseX = rect.left + rect.width / 2 - hostRect.left;
            var baseY = rect.top + rect.height / 2 - hostRect.top;
            var count = 14;

            for (var i = 0; i < count; i += 1) {
                var piece = document.createElement('span');
                var spread = 18 + Math.random() * 54;
                var angle = (Math.PI * 2 * i) / count + (Math.random() * 0.6 - 0.3);
                var tx = Math.cos(angle) * spread;
                var ty = Math.sin(angle) * spread - 22;
                var rot = (Math.random() * 320 - 160).toFixed(0) + 'deg';
                var sizeW = (5 + Math.random() * 4).toFixed(1) + 'px';
                var sizeH = (8 + Math.random() * 5).toFixed(1) + 'px';

                piece.className = 'mini-confetti';
                piece.style.left = baseX.toFixed(1) + 'px';
                piece.style.top = baseY.toFixed(1) + 'px';
                piece.style.width = sizeW;
                piece.style.height = sizeH;
                piece.style.background = confettiColors[Math.floor(Math.random() * confettiColors.length)];
                piece.style.setProperty('--tx', tx.toFixed(1) + 'px');
                piece.style.setProperty('--ty', ty.toFixed(1) + 'px');
                piece.style.setProperty('--rot', rot);
                piece.style.animationDuration = (560 + Math.random() * 220).toFixed(0) + 'ms';
                piece.style.animationDelay = (Math.random() * 90).toFixed(0) + 'ms';

                host.appendChild(piece);
                piece.addEventListener('animationend', function () {
                    if (this && this.parentNode) {
                        this.parentNode.removeChild(this);
                    }
                });
            }
        }

        balloons.forEach(function (balloon) {
            balloon.addEventListener('click', function () {
                if (balloon.classList.contains('is-popping') || balloon.classList.contains('is-popped')) {
                    return;
                }

                balloon.classList.add('is-popping');
                burstConfetti(balloon);
                showMessage();

                window.setTimeout(function () {
                    balloon.classList.remove('is-popping');
                    balloon.classList.add('is-popped');
                }, 400);
            });
        });
    }

    function initScrollPetals() {
        var host = document.getElementById('scrollPetalsLayer');
        if (!host) {
            return;
        }

        var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduceMotion) {
            return;
        }

        var maxPetals = 26;
        var activePetals = 0;
        var lastScrollY = window.scrollY || window.pageYOffset || 0;
        var lastEmitAt = 0;
        var emitCooldownMs = 140;

        function createPetal() {
            if (activePetals >= maxPetals && host.firstElementChild) {
                host.removeChild(host.firstElementChild);
                activePetals = Math.max(0, activePetals - 1);
            }

            var petal = document.createElement('span');
            var size = 8 + Math.random() * 8;
            var drift = -58 + Math.random() * 116;
            var duration = 9000 + Math.random() * 5500;
            var rotate = 180 + Math.random() * 340;
            var opacity = 0.14 + Math.random() * 0.22;

            petal.className = 'scroll-petal';
            petal.style.left = (Math.random() * 100).toFixed(2) + 'vw';
            petal.style.setProperty('--petal-size', size.toFixed(2) + 'px');
            petal.style.setProperty('--petal-drift', drift.toFixed(2) + 'px');
            petal.style.setProperty('--petal-duration', duration.toFixed(0) + 'ms');
            petal.style.setProperty('--petal-rotate', rotate.toFixed(0) + 'deg');
            petal.style.setProperty('--petal-opacity', opacity.toFixed(2));
            petal.style.animationDelay = (Math.random() * 220).toFixed(0) + 'ms';

            host.appendChild(petal);
            activePetals += 1;

            petal.addEventListener('animationend', function () {
                if (petal.parentNode) {
                    petal.parentNode.removeChild(petal);
                }
                activePetals = Math.max(0, activePetals - 1);
            });
        }

        function emitByScroll() {
            if (!body.classList.contains('gate-opened')) {
                return;
            }

            var currentY = window.scrollY || window.pageYOffset || 0;
            var delta = Math.abs(currentY - lastScrollY);
            lastScrollY = currentY;
            if (delta < 4) {
                return;
            }

            var now = Date.now();
            if (now - lastEmitAt < emitCooldownMs) {
                return;
            }
            lastEmitAt = now;

            var burstCount = 1 + Math.min(3, Math.floor(delta / 180));
            for (var i = 0; i < burstCount; i += 1) {
                createPetal();
            }
        }

        window.addEventListener('scroll', emitByScroll, { passive: true });
    }

    function initEventCountdown() {
        var host = document.getElementById('eventCountdown');
        if (!host) {
            return;
        }

        var targetMs = parseInt(host.getAttribute('data-target-ms') || '', 10);
        if (isNaN(targetMs) || targetMs <= Date.now()) {
            host.style.display = 'none';
            return;
        }

        var daysNode = document.getElementById('countdownDays');
        var hoursNode = document.getElementById('countdownHours');
        var minutesNode = document.getElementById('countdownMinutes');
        if (!daysNode || !hoursNode || !minutesNode) {
            return;
        }

        function pad2(value) {
            return value < 10 ? '0' + String(value) : String(value);
        }

        function renderCountdown() {
            var remainingMs = targetMs - Date.now();
            if (remainingMs <= 0) {
                host.style.display = 'none';
                return false;
            }

            var remainingMinutes = Math.floor(remainingMs / 60000);
            var days = Math.floor(remainingMinutes / (60 * 24));
            var hours = Math.floor((remainingMinutes % (60 * 24)) / 60);
            var minutes = remainingMinutes % 60;

            daysNode.textContent = String(days);
            hoursNode.textContent = pad2(hours);
            minutesNode.textContent = pad2(minutes);
            return true;
        }

        renderCountdown();
        var timerId = window.setInterval(function () {
            if (!renderCountdown()) {
                window.clearInterval(timerId);
            }
        }, 1000);
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
        var blessingToastNode = document.getElementById('babyPlayBlessingToast');
        var blessingCounterNode = document.getElementById('babyPlayBlessingCounter');
        var giggleAudio = document.getElementById('babyPlayAudio');
        var imageNode = widget.querySelector('.baby-play__avatar-img');
        var emojiNode = widget.querySelector('.baby-play__emoji');

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
        var blessingToastTimerId = 0;
        var moodSwapTimerId = 0;
        var moodResetTimerId = 0;
        var dragThreshold = 6;
        var viewportPadding = 8;
        var moodSequence = ['😊', '😂', '🥰', '😴'];
        var moodLabels = ['ખુશ', 'હસતું', 'પ્રેમાળ', 'ઊંઘતું'];
        var moodIndex = -1;
        var blessingStorageKey = 'babyPlayAshirwadCount:' + window.location.pathname;
        var blessingCount = 0;
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

        function readBlessingCount() {
            try {
                var rawValue = window.localStorage.getItem(blessingStorageKey);
                var parsed = parseInt(rawValue || '0', 10);
                if (!isNaN(parsed) && parsed >= 0) {
                    blessingCount = parsed;
                }
            } catch (error) {
                blessingCount = 0;
            }
        }

        function renderBlessingCount() {
            if (!blessingCounterNode) {
                return;
            }
            blessingCounterNode.textContent = 'આશીર્વાદ: ' + blessingCount;
        }

        function showBlessingToast() {
            if (blessingToastNode) {
                blessingToastNode.textContent = 'આશીર્વાદ મોકલાયો 💖';
            }
            widget.classList.add('show-blessing');
            if (blessingToastTimerId) {
                window.clearTimeout(blessingToastTimerId);
            }
            blessingToastTimerId = window.setTimeout(function () {
                widget.classList.remove('show-blessing');
            }, 1300);
        }

        function saveBlessingCount() {
            try {
                window.localStorage.setItem(blessingStorageKey, String(blessingCount));
            } catch (error) {
                // Ignore storage failures.
            }
        }

        function addBlessing() {
            blessingCount += 1;
            renderBlessingCount();
            saveBlessingCount();
            showBlessingToast();
            spawnHearts();
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

        function cycleMood() {
            if (!emojiNode) {
                return;
            }

            moodIndex = (moodIndex + 1) % moodSequence.length;

            if (moodSwapTimerId) {
                window.clearTimeout(moodSwapTimerId);
            }
            if (moodResetTimerId) {
                window.clearTimeout(moodResetTimerId);
            }

            widget.classList.remove('is-mood-popped');
            widget.classList.add('is-mood-changing');

            moodSwapTimerId = window.setTimeout(function () {
                emojiNode.textContent = moodSequence[moodIndex];
                avatarBtn.setAttribute('aria-label', 'બેબીનો મૂડ: ' + moodLabels[moodIndex]);
                avatarBtn.setAttribute('title', 'બેબીનો મૂડ: ' + moodLabels[moodIndex]);
                widget.classList.remove('is-mood-changing');
                widget.classList.add('is-mood-popped');
            }, 110);

            moodResetTimerId = window.setTimeout(function () {
                widget.classList.remove('is-mood-popped');
            }, 340);
        }

        function triggerLaugh(spawnHeartBurst) {
            var now = Date.now();
            if (now < activeUntil) {
                return;
            }

            activeUntil = now + cooldownMs;
            widget.classList.add('is-laughing');
            if (spawnHeartBurst !== false) {
                spawnHearts();
            }
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
            cycleMood();
            addBlessing();
            triggerLaugh(false);
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
        readBlessingCount();
        renderBlessingCount();

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
        initPhotoMemorySlider();
        initMiniBalloonInteraction();
        initScrollPetals();
        initEventCountdown();
        initBabyPlayWidget();
    }
})();
