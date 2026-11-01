// Homepage laptop: drag to rotate it in 3D; at rest it sways gently.
export function initLaptop() {
    const stage = document.querySelector('[data-laptop]');
    if (!stage || stage.dataset.ready) return;
    stage.dataset.ready = '1';
    const laptop = stage.querySelector('.home-laptop');
    const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let rx = -16;
    let ry = -28;
    let phase = 0;
    let dragging = null;
    let visible = true;
    let lastTime = performance.now();

    const render = () => {
        laptop.style.setProperty('--rx', `${rx}deg`);
        laptop.style.setProperty('--ry', `${ry + Math.sin(phase) * 8}deg`);
    };
    const frame = (time) => {
        if (!stage.isConnected) return;
        if (visible && !dragging && !still) phase += (time - lastTime) * 0.0006;
        lastTime = time;
        render();
        requestAnimationFrame(frame);
    };

    stage.addEventListener('pointerdown', (event) => {
        dragging = { x: event.clientX, y: event.clientY };
        stage.setPointerCapture(event.pointerId);
        stage.classList.add('is-dragging', 'was-dragged');
    });
    stage.addEventListener('pointermove', (event) => {
        if (!dragging) return;
        ry += (event.clientX - dragging.x) * 0.45;
        rx = Math.max(-45, Math.min(25, rx - (event.clientY - dragging.y) * 0.3));
        dragging = { x: event.clientX, y: event.clientY };
    });
    const release = () => { dragging = null; stage.classList.remove('is-dragging'); };
    stage.addEventListener('pointerup', release);
    stage.addEventListener('pointercancel', release);
    stage.addEventListener('dblclick', () => { rx = -16; ry = -28; });

    new IntersectionObserver(([entry]) => { visible = entry.isIntersecting; }).observe(stage);
    render();
    requestAnimationFrame(frame);
}
