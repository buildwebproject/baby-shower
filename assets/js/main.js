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

    if (body.getAttribute('data-page') === 'invite') {
        initShareButtons();
        initDownloadImage();
    }
})();
