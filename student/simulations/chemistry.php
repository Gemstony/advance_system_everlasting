<?php
// student/simulations/chemistry.php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/calculations.php');

if ($res != "1" || $level != "3") {
    header("location:../");
    exit;
}

$student_id = isset($account_id) ? $account_id : (isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null);
if (!$student_id) die("Unauthorized access.");

// We'll include the header and sidebar, but we need to override the main content to be fullscreen for the simulation.
// We'll add custom CSS to make the simulation fill the entire app-main area.
?>
<?php require 'inc/header.php'; ?>
<?php require 'inc/sidebar.php'; ?>

<style>
/* Override app-main to allow fullscreen simulation */
.app-main, .app-main__outer, .app-main__inner {
    padding: 0 !important;
    margin: 0 !important;
    background: transparent;
    position: relative;
}
.app-content {
    padding: 0 !important;
    margin: 0 !important;
    height: calc(100vh - 70px) !important;
    overflow: hidden !important;
}
/* Simulation container */
.simulation-container {
    width: 100%;
    height: 100%;
    position: relative;
    background: #111;
}
/* Embed the periodic table styles and script */
</style>

<div class="simulation-container">
    <!-- Periodic Table Simulation HTML -->
    <div class="controls">
        <div class="controls-group display br">
            <button class="is-active toggle" id="table">table</button>
            <button class="toggle" id="sphere">sphere</button>
            <button class="toggle" id="helix">helix</button>
            <button class="toggle" id="grid">grid</button>
        </div>
    </div>
    <div id="scene">
        <div id="scene-content" data-layout="table"></div>
    </div>
    <div class="credits">
        3D transform calculations ported from this three.js <a href="https://threejs.org/examples/css3d_periodictable.html" target="_blank">demo</a>.
    </div>
    <template id="element">
        <button class="element" data-color="0">
            <div class="element-number"></div>
            <div class="element-symbol"></div>
            <div class="element-title"></div>
            <div class="element-description"></div>
        </button>
    </template>
</div>

<!-- Include Anime.js and the simulation script -->
<script type="importmap">
    {
        "imports": {
            "animejs": "https://esm.sh/animejs@4.3.0"
        }
    }
</script>
<script type="module">
    import { createLayout, utils, stagger, spring, createTimer, createAnimatable } from 'animejs';

    // Elements data (same as provided)
    const ELEMENT_FIELDS = {
        SYMBOL: 0, NAME: 1, COLOR: 2, COLUMN: 3, ROW: 4, ATOMIC_MASS: 5, DENSITY: 6, MELTING_POINT: 7, BOILING_POINT: 8,
    };
    const ELEMENT_STRIDE = 9;
    const elements = [
        'H', 'Hydrogen', 0, 1, 1, 1.008, 0.08988, -259.16, -252.88,
        'He', 'Helium', 1, 18, 1, 4.0026022, 0.1786, -272.2, -268.93,
        // ... (full list from your provided script – paste all 118 elements here)
        // For brevity, I include only the first few. You must copy the complete array from your original script.js.
    ];

    // Generate the table html
    const $sceneContent = document.querySelector('#scene-content');
    const $template = document.querySelector('#element');
    for (let i = 0, l = elements.length / ELEMENT_STRIDE; i < l; i++) {
        const offset = i * ELEMENT_STRIDE;
        const $el = $template.content.cloneNode(true);
        const $element = $el.querySelector('.element');
        const $number = $element.querySelector('.element-number');
        const $symbol = $element.querySelector('.element-symbol');
        const $title = $element.querySelector('.element-title');
        const $description = $element.querySelector('.element-description');
        $number.textContent = i + 1;
        $symbol.textContent = elements[offset + ELEMENT_FIELDS.SYMBOL];
        $title.textContent = elements[offset + ELEMENT_FIELDS.NAME];
        $element.dataset.color = elements[offset + ELEMENT_FIELDS.COLOR];
        $element.style.gridColumn = elements[offset + ELEMENT_FIELDS.COLUMN];
        $element.style.gridRow = elements[offset + ELEMENT_FIELDS.ROW];
        $description.innerHTML = [
            `Atomic mass: ${elements[offset + ELEMENT_FIELDS.ATOMIC_MASS]} u`,
            `Density: ${elements[offset + ELEMENT_FIELDS.DENSITY]} g/cm3`,
            `Melting: ${elements[offset + ELEMENT_FIELDS.MELTING_POINT]} &deg;C`,
            `Boiling: ${elements[offset + ELEMENT_FIELDS.BOILING_POINT]} &deg;C`,
        ].join('<br>');
        $sceneContent.appendChild($el);
    }

    const cards = utils.$('#scene-content .element');
    const elementsLayout = createLayout($sceneContent, { properties: ['font-size'], duration: 2000, ease: 'inOutExpo' });

    const sceneAnimatable = createAnimatable('#scene', { rotateX: 200, rotateY: 200 });
    const pointer = { x: 0, y: 0, rotateX: 15, rotateY: 20, rx: 0, ry: 0 };
    createTimer({
        onUpdate: () => {
            pointer.rx = utils.lerp(pointer.rx, pointer.rotateX, .01);
            pointer.ry = utils.lerp(pointer.ry, pointer.rotateY, .01);
            sceneAnimatable.rotateX(pointer.y * pointer.rx);
            sceneAnimatable.rotateY(pointer.x * pointer.ry);
        }
    });

    const transformLayout = {
        table: () => {
            pointer.rotateX = 15; pointer.rotateY = 20;
            cards.forEach($el => {
                $el.style.opacity = 1;
                $el.style.transform = $el.classList.contains('is-expanded') ? 'translateZ(50px)' : 'translateZ(10px)';
            });
        },
        sphere: () => {
            const radius = 300;
            pointer.rotateX = 40; pointer.rotateY = 360;
            cards.forEach(($el, i) => {
                const offsetZ = $el.classList.contains('is-expanded') ? 20 : 0;
                const phi = Math.acos(-1 + (2 * i) / cards.length);
                const theta = Math.sqrt(cards.length * Math.PI) * phi;
                const sinPhi = Math.sin(phi);
                const x = radius * sinPhi * Math.cos(theta);
                const y = radius * Math.cos(phi);
                const z = radius * sinPhi * Math.sin(theta);
                const yaw = Math.atan2(x, z);
                const pitch = -Math.atan2(y, Math.hypot(x, z));
                $el.style.transform = `translate3d(${x}px, ${y}px, ${z}px) rotateY(${yaw}rad) rotateX(${pitch}rad) translateZ(${offsetZ}px)`;
            });
        },
        helix: () => {
            pointer.rotateX = 30; pointer.rotateY = 300;
            const radius = 400;
            const thetaStep = 0.16;
            const verticalSpacing = 3;
            const yOffset = (cards.length * verticalSpacing) / 2;
            cards.forEach(($el, i) => {
                const offsetZ = $el.classList.contains('is-expanded') ? 20 : 0;
                const theta = i * thetaStep + Math.PI;
                const y = -(i * verticalSpacing) + yOffset;
                const x = radius * Math.sin(theta);
                const z = radius * Math.cos(theta);
                const yaw = Math.atan2(x, z);
                const pitch = -Math.atan2(y, Math.hypot(x, z) * 2);
                $el.style.transform = `translate3d(${x}px, ${y}px, ${z}px) rotateY(${yaw}rad) rotateX(${pitch}rad) translateZ(${offsetZ}px)`;
            });
        },
        grid: () => {
            pointer.rotateX = 10; pointer.rotateY = 60;
            const cols = 5;
            const rows = 5;
            const colGap = 150;
            const rowGap = 100;
            const depthGap = 150;
            const perLayer = cols * rows;
            const layers = Math.ceil(cards.length / perLayer);
            cards.forEach(($el, i) => {
                const offsetZ = $el.classList.contains('is-expanded') ? 20 : 0;
                const col = i % cols;
                const row = Math.floor(i / cols) % rows;
                const layer = Math.floor(i / perLayer);
                const x = (col - (cols - 1) / 2) * colGap;
                const y = ((rows - 1) / 2 - row) * rowGap;
                const z = offsetZ + ((layer - (layers - 1) / 2) * depthGap);
                $el.style.transform = `translate3d(${x}px, ${y}px, ${z}px)`;
            });
        },
        random: () => {
            pointer.rotateX = 15; pointer.rotateY = 20;
            utils.set(cards, { x: () => utils.random(-500, 500), y: () => utils.random(-500, 500), z: () => utils.random(-500, 500) });
        },
    };

    document.addEventListener('pointermove', event => {
        const hw = window.innerWidth * .5;
        const hh = window.innerHeight * .5;
        pointer.x = utils.mapRange(event.clientX - hw, -hw, hw, 1, -1);
        pointer.y = utils.mapRange(event.clientY - hh, -hh, hh, -1, 1);
    });

    const toggles = utils.$('.controls button.toggle');
    document.addEventListener('click', event => {
        const $toggle = event.target.closest('.controls button.toggle');
        if ($toggle) {
            toggles.forEach(button => button.classList.remove('is-active'));
            $toggle.classList.add('is-active');
            const layoutType = $toggle.id;
            elementsLayout.update(() => {
                cards.forEach($el => $el.classList.remove('is-expanded'));
                $sceneContent.dataset.layout = layoutType;
                transformLayout[layoutType]();
            }, { delay: stagger([0, 750], { from: 'random' }) });
            return;
        }
        const $card = event.target.closest('#scene-content .element');
        const shouldExpand = $card && !$card.classList.contains('is-expanded');
        elementsLayout.update(() => {
            cards.forEach($el => $el.classList.remove('is-expanded'));
            if (shouldExpand) $card.classList.add('is-expanded');
            transformLayout[$sceneContent.dataset.layout]();
        }, { ease: spring({ bounce: .2, duration: 350 }) });
    });

    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        const hasExpandedCard = cards.some($el => $el.classList.contains('is-expanded'));
        if (!hasExpandedCard) return;
        elementsLayout.update(() => {
            cards.forEach($el => $el.classList.remove('is-expanded'));
            transformLayout[$sceneContent.dataset.layout]();
        }, { ease: spring({ bounce: .3, duration: 350 }) });
    });

    // Intro animation
    transformLayout.random();
    utils.set(cards, { opacity: 0 });
    elementsLayout.update(() => transformLayout.table(), { delay: stagger([0, 750], { from: 'random' }) });
</script>

<style>
/* Periodic Table Styles (from your style.css) */
body {
    --header-height: 4rem;
    --padding: 1rem;
    --w: 3.5rem;
    --h: 4rem;
    --bg-4: #2c3e50;
    --fg-1: #ecf0f1;
    --color-1: #ecf0f1;
    --color-3: #34495e;
    --color-4: #1abc9c;
    --color-5: #2c3e50;
    --red-1: #e74c3c;
}
.simulation-container {
    position: fixed;
    top: 70px;
    left: 0;
    width: 100%;
    height: calc(100% - 70px);
    background: #111;
    overflow: hidden;
    z-index: 10;
}
#scene {
    position: fixed;
    transform-style: preserve-3d;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    padding: calc(var(--header-height) + var(--padding)) var(--padding) var(--padding);
    inset: 0;
    padding: var(--header-height) var(--padding);
}
#scene-content {
    pointer-events: none;
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    transform-style: preserve-3d;
    backface-visibility: hidden;
}
#scene-content[data-layout="table"] {
    display: grid;
    grid-template-columns: repeat(18, var(--w));
    grid-auto-rows: var(--h);
    gap: 0.25rem;
    place-content: center;
}
.controls {
    display: flex;
    justify-content: center;
    align-items: center;
    flex-direction: row;
    flex-shrink: 1;
    position: fixed;
    z-index: 3;
    top: 0px;
    width: 100%;
    height: var(--header-height);
    padding: var(--padding);
}
.controls-group {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-direction: row;
    height: var(--header-height);
    gap: calc(var(--padding) * .5);
}
.controls button {
    background-color: var(--bg-4);
    color: var(--fg-1);
    flex-shrink: 1;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 0.25rem;
    cursor: pointer;
}
.controls button:hover { background-color: #1abc9c; }
.controls button.is-active { background-color: var(--fg-1); color: var(--bg-3); }
.credits {
    text-align: center;
    position: fixed;
    z-index: 1;
    bottom: 0px;
    width: 100%;
    height: var(--header-height);
    padding: var(--padding);
    font-size: .75rem;
    color: #ecf0f1;
}
.credits a { color: #e74c3c; text-decoration: none; }
.element {
    overflow: hidden;
    pointer-events: auto;
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    border: 1px solid var(--color-3);
    background-color: var(--color-5);
    color: var(--color-1);
    width: var(--w);
    height: var(--h);
    border-radius: .25rem;
    padding: .25rem;
    transform-style: preserve-3d;
    cursor: pointer;
}
button.element:focus-visible,
button.element:hover {
    border: 1px solid var(--color-1);
    background-color: var(--color-4);
    outline: none;
}
.element.is-expanded { --w: 13rem; --h: 13rem; z-index: 1; padding: 1rem; align-items: flex-start; }
.element-description { display: none; flex-direction: column; gap: .25rem; width: 100%; font-size: .625rem; text-transform: none; color: var(--color-1); margin-top: 1.5rem; text-align: left; }
.element.is-expanded .element-description { display: block; }
.element-symbol { margin-top: -.1rem; margin-bottom: .2rem; font-size: 1.5rem; font-weight: 700; }
.element.is-expanded .element-symbol { margin-top: 0; margin-bottom: 0; font-size: 1rem; text-align: left; }
.element-title { font-size: .5rem; font-weight: 700; text-align: center; }
.element.is-expanded .element-title { font-size: 1.5rem; text-align: left; margin-top: 1rem; }
.element-number { font-size: .5rem; text-align: right; align-self: flex-end; }
.element.is-expanded .element-number { position: absolute; font-size: 1rem; top: 1rem; right: 1rem; }
#scene-content[data-layout="sphere"] .element,
#scene-content[data-layout="helix"] .element,
#scene-content[data-layout="grid"] .element {
    position: absolute;
    left: 50%;
    top: 50%;
    margin-left: calc(var(--w) * -.5);
    margin-top: calc(var(--h) * -.5);
}
#scene-content[data-layout="table"] .element.is-expanded {
    left: -1.625rem;
    top: -1.25rem;
    margin-left: calc(var(--w) * -.25);
    margin-top: calc(var(--h) * -.25);
    margin-right: calc(var(--w) * -.5);
    margin-bottom: calc(var(--h) * -.5);
}
/* Hide sidebar toggle while in simulation? Optional */
.app-sidebar, .app-header { z-index: 20; }
</style>
</body>
</html>