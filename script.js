const shell = document.getElementById("invitationShell");
const openButton = document.getElementById("openButton");
const soundToggle = document.getElementById("soundToggle");
const statusText = document.getElementById("statusText");

let hasOpened = false;
let audioContext = null;

function setStatus(message) {
    statusText.textContent = message;
}

function playRibbonSound() {
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtx) return;

    if (!audioContext) {
        audioContext = new AudioCtx();
    }

    if (audioContext.state === "suspended") {
        audioContext.resume();
    }

    const now = audioContext.currentTime;
    const master = audioContext.createGain();
    master.gain.setValueAtTime(0.0001, now);
    master.gain.exponentialRampToValueAtTime(0.26, now + 0.04);
    master.gain.exponentialRampToValueAtTime(0.0001, now + 0.95);
    master.connect(audioContext.destination);

    const notes = [523.25, 659.25, 783.99];
    notes.forEach((freq, index) => {
        const osc = audioContext.createOscillator();
        const noteGain = audioContext.createGain();
        const start = now + index * 0.08;
        const end = start + 0.45;

        osc.type = "triangle";
        osc.frequency.setValueAtTime(freq, start);
        osc.frequency.exponentialRampToValueAtTime(freq * 1.015, end);

        noteGain.gain.setValueAtTime(0.0001, start);
        noteGain.gain.exponentialRampToValueAtTime(0.75, start + 0.04);
        noteGain.gain.exponentialRampToValueAtTime(0.0001, end);

        osc.connect(noteGain);
        noteGain.connect(master);
        osc.start(start);
        osc.stop(end + 0.02);
    });
}

function startOpeningSequence() {
    if (hasOpened) return;
    hasOpened = true;
    openButton.disabled = true;

    shell.classList.add("is-untying");
    setStatus("રિબન ખુલી રહી છે.");

    if (soundToggle.checked) {
        playRibbonSound();
    }

    window.setTimeout(() => {
        shell.classList.add("is-opening");
        setStatus("દરવાજા ખુલ્લા થઈ રહ્યા છે.");
    }, 820);

    window.setTimeout(() => {
        shell.classList.add("is-opened");
        setStatus("આમંત્રણ ખુલ્લું છે.");
    }, 2050);
}

openButton.addEventListener("click", startOpeningSequence);
