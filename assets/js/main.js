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

    initOpeningExperience();

    if (body.getAttribute('data-page') === 'invite') {
        initShareButtons();
        initDownloadImage();
    }
})();
