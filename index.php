<?php
session_start();
require_once('db/config.php');
require_once('const/school.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SRMS - Login</title>
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
    <link type="text/css" rel="stylesheet" href="loader/waitMe.css">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons.css" rel="stylesheet">
    <link href="css/tooplate-kool-form-pack.css" rel="stylesheet">
<style>
/* =========================================================
   BASE
   ========================================================= */
*{margin:0;padding:0;box-sizing:border-box;}
html,body{
    height:100%;
    font-family:'Inter','Segoe UI',system-ui,sans-serif;
    background:#05070c;color:#e6f7ff;overflow:hidden;
}
main{position:relative;height:100vh;height:100dvh;width:100vw;overflow:hidden;}

/* =========================================================
   BACKGROUND
   ========================================================= */
.video-wrap{position:absolute;inset:0;z-index:0;overflow:hidden;}
.video-wrap video{
    width:100%;height:100%;object-fit:cover;
    opacity:0.58;
    filter:brightness(0.68) saturate(1.05) contrast(1.02);
}
#shader-canvas{position:absolute;inset:0;z-index:1;pointer-events:none;opacity:0.32;mix-blend-mode:screen;}
#stars-canvas{position:absolute;inset:0;z-index:1;pointer-events:none;opacity:0.85;}

.bubbles-layer{position:absolute;inset:0;z-index:1;pointer-events:none;overflow:hidden;}
.bubble{
    position:absolute;border-radius:50%;
    background:radial-gradient(circle at 30% 30%,rgba(180,255,240,0.55),rgba(30,180,160,0.08) 60%,transparent 70%);
    filter:blur(0.5px);opacity:0.28;animation:bubbleRise linear infinite;
}
@keyframes bubbleRise{
    0%{transform:translateY(20px) scale(0.9);opacity:0;}
    15%{opacity:0.35;}
    85%{opacity:0.28;}
    100%{transform:translateY(-110vh) scale(1.05);opacity:0;}
}

.cursor-reveal{
    position:fixed;left:0;top:0;
    width:340px;height:340px;
    margin:-170px 0 0 -170px;
    border-radius:50%;pointer-events:none;z-index:2;
    backdrop-filter:brightness(2.2) saturate(1.35) contrast(1.05);
    -webkit-backdrop-filter:brightness(2.2) saturate(1.35) contrast(1.05);
    -webkit-mask-image:radial-gradient(circle, #000 0%, #000 32%, transparent 78%);
    mask-image:radial-gradient(circle, #000 0%, #000 32%, transparent 78%);
    opacity:0;transition:opacity 0.6s ease;
    transform:translate3d(-9999px,-9999px,0);
    will-change:transform;
}
body.torch-on .cursor-reveal{ opacity:1; }

.cursor-light{
    position:fixed;left:0;top:0;
    width:420px;height:420px;margin:-210px 0 0 -210px;
    border-radius:50%;pointer-events:none;
    background:radial-gradient(circle,
        rgba(220,250,245,0.28) 0%,
        rgba(200,240,235,0.16) 26%,
        rgba(170,220,220,0.08) 50%,
        rgba(120,180,190,0.03) 72%,
        transparent 92%);
    mix-blend-mode:screen;filter:blur(10px);opacity:0;
    transition:opacity 0.65s ease;z-index:2;will-change:transform;
    transform:translate3d(-9999px,-9999px,0);
}
body.torch-on .cursor-light{ opacity:1; }

.swal2-container,
.swal2-popup,
.swal2-confirm,
.swal2-actions{ display:none !important; }

/* =========================================================
   SCENE
   ========================================================= */
.scene-container{
    position:relative;z-index:3;
    min-height:100vh;min-height:100dvh;
    display:flex;align-items:center;justify-content:center;
    gap:clamp(1rem,3vw,3rem);
    padding:clamp(0.5rem,1.2vw,1.2rem);
}
.visual-scene{
    position:relative;
    width:clamp(280px,34vw,400px);
    height:clamp(520px,92vh,780px);
    flex-shrink:0;
}
.form-scene{position:relative;width:100%;max-width:400px;perspective:900px;}

/* =========================================================
   LAMP
   ========================================================= */
.lamp-assembly{
    position:absolute;top:-6px;left:50%;
    transform:translateX(-50%) translateY(-40px);
    opacity:0;width:220px;
    display:flex;flex-direction:column;align-items:center;
    animation:lampAppear 1.4s cubic-bezier(0.23,1,0.32,1) 0.3s forwards;
    z-index:6;
}
@keyframes lampAppear{to{opacity:1;transform:translateX(-50%) translateY(0);}}

.lamp-cable{
    width:2px;height:60px;
    background:linear-gradient(180deg,rgba(140,160,180,0.15),rgba(140,160,180,0.75));
    border-radius:2px;box-shadow:0 0 6px rgba(120,200,220,0.35);
}
.lamp-fixture{
    position:relative;width:70px;height:24px;
    background:linear-gradient(180deg,#1c2430,#0b1016);
    border-radius:8px 8px 4px 4px;
    border:1px solid rgba(120,200,220,0.35);
    box-shadow:inset 0 -4px 10px rgba(0,0,0,0.6),0 0 12px rgba(34,211,238,0.25);
}
.lamp-shade{
    position:absolute;top:100%;left:50%;transform:translateX(-50%);
    width:110px;height:52px;
    background:linear-gradient(180deg,#14212b 0%,#0a1219 60%,#050a0e 100%);
    border-radius:0 0 60px 60px / 0 0 70px 70px;
    border:1px solid rgba(120,200,220,0.35);
    border-top:none;overflow:hidden;
    box-shadow:0 8px 20px rgba(0,0,0,0.6),inset 0 -6px 20px rgba(0,0,0,0.75);
}
.lamp-shade::after{
    content:"";position:absolute;left:8%;right:8%;bottom:4px;height:3px;
    background:linear-gradient(90deg,transparent,rgba(140,255,235,0.65),transparent);
    filter:blur(1px);border-radius:50%;
}
.lamp-bulb{
    position:absolute;top:50%;left:50%;
    transform:translate(-50%,-50%);width:46px;height:26px;
    background:radial-gradient(ellipse at 50% 30%,#d5fff4 0%,#6df0d4 35%,#22d3ee 70%,#0e7d8c 100%);
    border-radius:50%;opacity:0;
    box-shadow:
        0 0 24px 6px rgba(120,255,230,0.85),
        0 0 60px 20px rgba(34,211,238,0.55),
        0 0 120px 50px rgba(34,211,238,0.22);
    animation:bulbOn 1.2s ease-out 1.0s forwards;
}
@keyframes bulbOn{
    0%{opacity:0;transform:translate(-50%,-50%) scale(0.4);}
    60%{opacity:1;transform:translate(-50%,-50%) scale(1.08);}
    100%{opacity:1;transform:translate(-50%,-50%) scale(1);}
}

.light-cone{
    position:absolute;top:140px;left:50%;
    transform:translateX(-50%) scaleY(0.3);
    transform-origin:top center;
    width:clamp(260px,92%,360px);
    height:clamp(380px,88%,640px);
    background:radial-gradient(ellipse at 50% 0%,
        rgba(140,255,235,0.55) 0%,
        rgba(34,211,238,0.28) 30%,
        rgba(34,211,238,0.10) 60%,
        rgba(34,211,238,0) 100%);
    clip-path:polygon(38% 0%,62% 0%,100% 100%,0% 100%);
    filter:blur(10px);opacity:0;pointer-events:none;
    animation:coneExpand 1.6s cubic-bezier(0.23,1,0.32,1) 1.1s forwards;
    z-index:1;
}
@keyframes coneExpand{
    0%{opacity:0;transform:translateX(-50%) scaleY(0.3);}
    100%{opacity:1;transform:translateX(-50%) scaleY(1);}
}

/* =========================================================
   ROBOT + CHORE STAGE
   ========================================================= */
/* Stage = the wide horizontal lane the robot walks along */
.robot-stage{
    position:absolute;
    left:0;right:0;
    bottom:-4%;
    height:44%;
    z-index:3;
    pointer-events:none;
    overflow:visible;
}

.robot-wrapper{
    position:absolute;
    bottom:0;
    left:50%;
    transform:translateX(-50%) translateY(30px);
    width:52%;max-width:200px;
    aspect-ratio: 1 / 1;
    opacity:0;
    animation:robotAppear 1.4s cubic-bezier(0.23,1,0.32,1) 1.5s forwards;
    z-index:3;
    transition: transform 0.9s cubic-bezier(0.34,1.2,0.64,1), opacity 0.6s ease, filter 0.5s ease;
    will-change: transform;
}
@keyframes robotAppear{
    0%{opacity:0;transform:translateX(-50%) translateY(30px) scale(0.72);}
    100%{opacity:1;transform:translateX(-50%) translateY(0) scale(1);}
}

.robot-floater{
    position:relative;
    width:100%;height:100%;
    animation:robotFloat 4.5s ease-in-out 3s infinite;
}
@keyframes robotFloat{
    0%,100%{transform:translateY(0);}
    50%{transform:translateY(-8px);}
}

/* Robot walking (translates on the stage) */
body.robot-walking .robot-wrapper{
    transition: transform var(--walk-dur, 3.2s) cubic-bezier(0.45, 0, 0.55, 1);
}
body.robot-walking .robot-floater{
    animation: robotWalkBob 0.5s ease-in-out infinite;
}
@keyframes robotWalkBob{
    0%,100%{ transform: translateY(0) rotate(-1deg); }
    50%    { transform: translateY(-5px) rotate(1deg); }
}

/* Chore: sweeping */
body.chore-sweep .robot-floater{
    animation: sweepBob 1.4s ease-in-out infinite;
}
@keyframes sweepBob{
    0%,100%{ transform: translateY(0) rotate(-2deg); }
    50%    { transform: translateY(-4px) rotate(2deg); }
}
.robot-broom{
    position:absolute;
    bottom:6%;right:-12%;
    width:clamp(44px, 18%, 68px);
    opacity:0;
    transform: translate(4px,10px) rotate(20deg);
    transform-origin: 50% 100%;
    transition: opacity 0.5s ease, transform 0.5s cubic-bezier(0.34,1.56,0.64,1);
    z-index:5;
    pointer-events:none;
}
body.chore-sweep .robot-broom{
    opacity:1;
    transform: translate(0,0) rotate(20deg);
    animation: broomSweep 1.4s ease-in-out infinite;
}
@keyframes broomSweep{
    0%,100%{ transform: translate(0,0) rotate(14deg); }
    50%    { transform: translate(-4px,2px) rotate(26deg); }
}

/* Chore: dusting */
.robot-duster{
    position:absolute;
    bottom:14%;right:-14%;
    width:clamp(40px, 16%, 60px);
    opacity:0;
    transform: translate(4px,10px) rotate(-15deg);
    transform-origin: 50% 100%;
    transition: opacity 0.5s ease, transform 0.5s cubic-bezier(0.34,1.56,0.64,1);
    z-index:5;
    pointer-events:none;
}
body.chore-dust .robot-duster{
    opacity:1;
    transform: translate(0,0) rotate(-15deg);
    animation: dusterShake 0.6s ease-in-out infinite;
}
@keyframes dusterShake{
    0%,100%{ transform: translate(0,0) rotate(-16deg); }
    50%    { transform: translate(0,-3px) rotate(-10deg); }
}

/* Chore: dust particles rising from the floor while sweeping/dusting */
.chore-dust-layer{
    position:absolute;
    inset:auto 0 0 0;
    height:60%;
    pointer-events:none;
    overflow:visible;
    z-index:2;
}
.dust-puff{
    position:absolute;
    bottom:0;
    width:10px;height:10px;
    border-radius:50%;
    background: radial-gradient(circle, rgba(200,235,230,0.55) 0%, rgba(200,235,230,0) 70%);
    opacity:0;
    animation: dustRise 1.6s ease-out forwards;
    pointer-events:none;
}
@keyframes dustRise{
    0%   { opacity:0;   transform: translate(0,0) scale(0.4); }
    25%  { opacity:0.7; }
    100% { opacity:0;   transform: translate(var(--dx,0px), -70px) scale(1.4); }
}

.robot-glow-floor{
    position:absolute;bottom:-10%;left:50%;transform:translateX(-50%);
    width:70%;height:26px;
    background:radial-gradient(ellipse at center,rgba(34,211,238,0.55),rgba(34,211,238,0) 70%);
    filter:blur(8px);z-index:-1;
    transition:background 0.55s ease;
}

svg.robot{
    width:100%;height:100%;display:block;overflow:visible;
    filter:
        drop-shadow(0 20px 30px rgba(0,0,0,0.55))
        drop-shadow(0 0 22px rgba(34,211,238,0.55))
        drop-shadow(0 0 46px rgba(34,211,238,0.28));
    transition:filter 0.55s ease;
    transform-origin:50% 50%;
}
.pupil{ transition:transform 0.08s linear, opacity 0.22s ease;will-change:transform; }
.eye   { transition:transform 0.35s ease;transform-box:fill-box;transform-origin:center; }
.eyebrow{ transition:transform 0.3s ease, opacity 0.3s ease; opacity:0.85; }

/* mouths */
.mouth-happy{ transition:opacity 0.25s ease; }
.mouth-sad  { opacity:0;transition:opacity 0.25s ease; }
.mouth-o    { opacity:0;transition:opacity 0.25s ease; }
.mouth-think{ opacity:0;transition:opacity 0.25s ease; }
.mouth-scared{ opacity:0;transition:opacity 0.25s ease; }
.mouth-sweet{ opacity:0;transition:opacity 0.25s ease; }
.mouth-laugh{ opacity:0;transition:opacity 0.25s ease; }
.mouth-focus{ opacity:0;transition:opacity 0.25s ease; }
.mouth-yawn { opacity:0;transition:opacity 0.25s ease; }
.mouth-angry{ opacity:0;transition:opacity 0.25s ease; }
.mouth-love { opacity:0;transition:opacity 0.25s ease; }
.mouth-chore{ opacity:0;transition:opacity 0.25s ease; }

.blush{ transition:opacity 0.35s ease; opacity:0; }
.heart{ transform-box: fill-box; transform-origin: center; opacity:0; transition:opacity 0.3s ease; }

/* States */
body.robot-happy .robot-floater{ animation:happyBounce 0.9s cubic-bezier(0.23,1,0.32,1) 1; }
@keyframes happyBounce{
    0%,100%{ transform:translateY(0); }
    35%    { transform:translateY(-12px); }
    65%    { transform:translateY(-4px); }
}
body.robot-happy .eye{ transform:scaleY(0.55); }
body.robot-happy .blush{ opacity:0.85; }

body.robot-laughing .robot-floater{ animation: laughJump 1.6s cubic-bezier(0.23,1,0.32,1) infinite; }
@keyframes laughJump{
    0%,100%{ transform: translateY(0) rotate(0deg); }
    25%    { transform: translateY(-14px) rotate(-3deg); }
    50%    { transform: translateY(-2px) rotate(0deg); }
    75%    { transform: translateY(-10px) rotate(3deg); }
}
body.robot-laughing .eye{ transform: scaleY(0.25); }
body.robot-laughing .eyebrow{ transform: translateY(-3px) rotate(-6deg); }
body.robot-laughing .mouth-happy,
body.robot-laughing .mouth-sad,
body.robot-laughing .mouth-o,
body.robot-laughing .mouth-think,
body.robot-laughing .mouth-scared,
body.robot-laughing .mouth-sweet,
body.robot-laughing .mouth-focus,
body.robot-laughing .mouth-yawn,
body.robot-laughing .mouth-angry,
body.robot-laughing .mouth-love,
body.robot-laughing .mouth-chore{ opacity: 0 !important; }
body.robot-laughing .mouth-laugh{ opacity: 1 !important; }
body.robot-laughing .blush{ opacity: 1; }

body.robot-sad svg.robot{
    filter:
        drop-shadow(0 20px 30px rgba(0,0,0,0.55))
        drop-shadow(0 0 22px rgba(239,71,111,0.55))
        drop-shadow(0 0 46px rgba(239,71,111,0.22));
}
body.robot-sad .eye{ transform:translateY(2px) scaleY(0.78); }
body.robot-sad .eyebrow-left { transform: translate(-2px, 3px) rotate(-14deg); }
body.robot-sad .eyebrow-right{ transform: translate( 2px, 3px) rotate( 14deg); }
body.robot-sad .mouth-happy{ opacity:0; }
body.robot-sad .mouth-sad  { opacity:1; }

body.robot-focus .eye{ transform: scaleY(0.72); }
body.robot-focus .mouth-happy,
body.robot-focus .mouth-sad,
body.robot-focus .mouth-o,
body.robot-focus .mouth-think,
body.robot-focus .mouth-scared,
body.robot-focus .mouth-sweet,
body.robot-focus .mouth-laugh,
body.robot-focus .mouth-yawn,
body.robot-focus .mouth-angry,
body.robot-focus .mouth-love,
body.robot-focus .mouth-chore{ opacity: 0 !important; }
body.robot-focus .mouth-focus{ opacity: 1 !important; }

body.robot-winking .eye-left  { transform: scaleY(0.08) !important; }
body.robot-winking .eye-right { transform: scaleY(1) !important; }
body.robot-winking .blush{ opacity: 0.75; }

body.robot-yawning svg.robot{ animation: robotStretch 2.2s ease-in-out infinite; }
@keyframes robotStretch{
    0%,100% { transform: translateY(0) scale(1); }
    50%     { transform: translateY(-3px) scale(1.02); }
}
body.robot-yawning .eye{ transform: scaleY(0.18); }
body.robot-yawning .mouth-happy,
body.robot-yawning .mouth-sad,
body.robot-yawning .mouth-o,
body.robot-yawning .mouth-think,
body.robot-yawning .mouth-scared,
body.robot-yawning .mouth-sweet,
body.robot-yawning .mouth-laugh,
body.robot-yawning .mouth-focus,
body.robot-yawning .mouth-angry,
body.robot-yawning .mouth-love,
body.robot-yawning .mouth-chore{ opacity: 0 !important; }
body.robot-yawning .mouth-yawn{ opacity: 1 !important; }

body.robot-surprised svg.robot{ animation: robotJolt 0.4s cubic-bezier(0.34,1.56,0.64,1) 1; }
@keyframes robotJolt{
    0%   { transform: translateY(0) scale(1); }
    40%  { transform: translateY(-14px) scale(1.06); }
    100% { transform: translateY(0) scale(1); }
}
body.robot-surprised .eye{ transform: scale(1.28); }
body.robot-surprised .eyebrow-left { transform: translateY(-4px) rotate(-10deg); }
body.robot-surprised .eyebrow-right{ transform: translateY(-4px) rotate( 10deg); }
body.robot-surprised .mouth-happy,
body.robot-surprised .mouth-sad,
body.robot-surprised .mouth-think,
body.robot-surprised .mouth-scared,
body.robot-surprised .mouth-sweet,
body.robot-surprised .mouth-laugh,
body.robot-surprised .mouth-focus,
body.robot-surprised .mouth-yawn,
body.robot-surprised .mouth-angry,
body.robot-surprised .mouth-love,
body.robot-surprised .mouth-chore{ opacity: 0 !important; }
body.robot-surprised .mouth-o{ opacity: 1 !important; }

body.robot-angry svg.robot{
    filter:
        drop-shadow(0 20px 30px rgba(0,0,0,0.55))
        drop-shadow(0 0 26px rgba(239,71,111,0.9))
        drop-shadow(0 0 60px rgba(185,28,28,0.6));
}
body.robot-angry .eye{ transform: scaleY(0.75); }
body.robot-angry .eyebrow-left { transform: translateY(2px) rotate(18deg); }
body.robot-angry .eyebrow-right{ transform: translateY(2px) rotate(-18deg); }
body.robot-angry .mouth-happy,
body.robot-angry .mouth-sad,
body.robot-angry .mouth-o,
body.robot-angry .mouth-think,
body.robot-angry .mouth-scared,
body.robot-angry .mouth-sweet,
body.robot-angry .mouth-laugh,
body.robot-angry .mouth-focus,
body.robot-angry .mouth-yawn,
body.robot-angry .mouth-love,
body.robot-angry .mouth-chore{ opacity: 0 !important; }
body.robot-angry .mouth-angry{ opacity: 1 !important; }

body.robot-love .blush{ opacity: 1; }
body.robot-love .eye{ transform: scaleY(0.5); }
body.robot-love .heart{ opacity: 1; animation: heartFloat 1.8s ease-out infinite; }
@keyframes heartFloat{
    0%   { opacity:0; transform: translateY(6px) scale(0.4); }
    25%  { opacity:1; transform: translateY(0) scale(1); }
    100% { opacity:0; transform: translateY(-24px) scale(1.2); }
}
body.robot-love .mouth-happy,
body.robot-love .mouth-sad,
body.robot-love .mouth-o,
body.robot-love .mouth-think,
body.robot-love .mouth-scared,
body.robot-love .mouth-sweet,
body.robot-love .mouth-laugh,
body.robot-love .mouth-focus,
body.robot-love .mouth-yawn,
body.robot-love .mouth-angry,
body.robot-love .mouth-chore{ opacity: 0 !important; }
body.robot-love .mouth-love{ opacity: 1 !important; }

/* Chore face: focused, tongue-out determination */
body.robot-chore .eye{ transform: scaleY(0.65); }
body.robot-chore .eyebrow-left { transform: translateY(-1px) rotate(-4deg); }
body.robot-chore .eyebrow-right{ transform: translateY(-1px) rotate( 4deg); }
body.robot-chore .mouth-happy,
body.robot-chore .mouth-sad,
body.robot-chore .mouth-o,
body.robot-chore .mouth-think,
body.robot-chore .mouth-scared,
body.robot-chore .mouth-sweet,
body.robot-chore .mouth-laugh,
body.robot-chore .mouth-focus,
body.robot-chore .mouth-yawn,
body.robot-chore .mouth-angry,
body.robot-chore .mouth-love{ opacity: 0 !important; }
body.robot-chore .mouth-chore{ opacity: 1 !important; }

body.robot-touched .eye{
    transform:scaleY(0.06) !important;
    transition:transform 0.18s ease-out;
}
body.robot-touched .pupil{ opacity:0; transition:opacity 0.16s ease-out; }

.sunglasses{
    transform-box: view-box;
    transform-origin: 100px 95px;
    transform: translateY(-34px) scale(0.9);
    opacity: 0; pointer-events: none;
    transition: opacity 0.3s ease, transform 0.55s cubic-bezier(0.34, 1.56, 0.64, 1);
}
body.robot-glasses .sunglasses{ opacity:1; transform: translateY(0) scale(1); }
body.robot-glasses svg.robot{ filter: hue-rotate(-135deg) saturate(1.9) brightness(1.15) contrast(1.05); }
body.robot-glasses .robot-glow-floor{ background:radial-gradient(ellipse at center,rgba(255,170,30,0.55),rgba(255,120,0,0) 70%); }
body.robot-glasses .robot-torch{
    filter: drop-shadow(0 10px 22px rgba(0,0,0,0.55)) drop-shadow(0 0 18px rgba(255,200,90,0.65));
}

body.robot-reset svg.robot{
    filter:
        drop-shadow(0 20px 30px rgba(0,0,0,0.55))
        drop-shadow(0 0 26px rgba(220,38,38,0.95))
        drop-shadow(0 0 60px rgba(185,28,28,0.7))
        drop-shadow(0 0 120px rgba(127,29,29,0.45));
    animation: robotTerrified 0.26s ease-in-out infinite;
}
@keyframes robotTerrified{
    0%, 100% { transform: translateX(0) translateY(0); }
    25%      { transform: translateX(-2.6px) translateY(0.5px); }
    75%      { transform: translateX( 2.6px) translateY(-0.5px); }
}
body.robot-reset .eye-left,
body.robot-reset .eye-right{ transform: scale(1.34); transform-box: fill-box; transform-origin: center; }
body.robot-reset .eyebrow-left { transform: translate(-3px,-2px) rotate(-16deg); }
body.robot-reset .eyebrow-right{ transform: translate( 3px,-2px) rotate( 16deg); }
body.robot-reset .mouth-happy,
body.robot-reset .mouth-sad,
body.robot-reset .mouth-think,
body.robot-reset .mouth-o,
body.robot-reset .mouth-sweet,
body.robot-reset .mouth-laugh,
body.robot-reset .mouth-focus,
body.robot-reset .mouth-yawn,
body.robot-reset .mouth-angry,
body.robot-reset .mouth-love,
body.robot-reset .mouth-chore{ opacity:0 !important; }
body.robot-reset .mouth-scared{ opacity:1 !important; }
body.robot-reset .robot-glow-floor{ background:radial-gradient(ellipse at center,rgba(220,38,38,0.8),rgba(127,29,29,0) 70%); }
body.robot-reset .robot-torch{
    filter: drop-shadow(0 10px 22px rgba(0,0,0,0.55)) drop-shadow(0 0 18px rgba(248,113,113,0.75));
}

body.submitting .mouth-happy,
body.submitting .mouth-sad,
body.submitting .mouth-think,
body.submitting .mouth-scared,
body.submitting .mouth-sweet,
body.submitting .mouth-laugh,
body.submitting .mouth-focus,
body.submitting .mouth-yawn,
body.submitting .mouth-angry,
body.submitting .mouth-love,
body.submitting .mouth-chore{ opacity:0 !important; }
body.submitting .mouth-o{ opacity:1 !important; }
svg.robot.eye-dim .eye{ transform:scaleY(0.85); }

body.torch-thinking .eye-left,
body.torch-thinking .eye-right{ transform:translateY(-2px); }
body.torch-thinking .mouth-happy,
body.torch-thinking .mouth-sad,
body.torch-thinking .mouth-o,
body.torch-thinking .mouth-scared,
body.torch-thinking .mouth-sweet,
body.torch-thinking .mouth-laugh,
body.torch-thinking .mouth-focus,
body.torch-thinking .mouth-yawn,
body.torch-thinking .mouth-angry,
body.torch-thinking .mouth-love,
body.torch-thinking .mouth-chore{ opacity:0 !important; }
body.torch-thinking .mouth-think{ opacity:1 !important; }

body.torch-shocked .eye-left,
body.torch-shocked .eye-right{ transform:scale(1.16); }
body.torch-shocked .mouth-happy,
body.torch-shocked .mouth-sad,
body.torch-shocked .mouth-think,
body.torch-shocked .mouth-scared,
body.torch-shocked .mouth-sweet,
body.torch-shocked .mouth-laugh,
body.torch-shocked .mouth-focus,
body.torch-shocked .mouth-yawn,
body.torch-shocked .mouth-angry,
body.torch-shocked .mouth-love,
body.torch-shocked .mouth-chore{ opacity:0 !important; }
body.torch-shocked .mouth-o{ opacity:1 !important; }

body.torch-wondering .eye-left { transform:translate(-2px,-1px); }
body.torch-wondering .eye-right{ transform:translate( 2px,-1px); }
body.torch-wondering .mouth-happy,
body.torch-wondering .mouth-sad,
body.torch-wondering .mouth-o,
body.torch-wondering .mouth-scared,
body.torch-wondering .mouth-sweet,
body.torch-wondering .mouth-laugh,
body.torch-wondering .mouth-focus,
body.torch-wondering .mouth-yawn,
body.torch-wondering .mouth-angry,
body.torch-wondering .mouth-love,
body.torch-wondering .mouth-chore{ opacity:0 !important; }
body.torch-wondering .mouth-think{ opacity:1 !important; }

/* =========================================================
   TORCH
   ========================================================= */
.robot-torch{
    position:absolute;
    bottom:6%;right:-8%;
    width:clamp(46px, 19%, 72px);
    aspect-ratio: 80 / 120;
    z-index:5;
    opacity:0;
    transform:translate(10px, 22px) scale(0.85);
    transform-origin:50% 100%;
    transition: opacity 0.55s ease, transform 0.65s cubic-bezier(0.34,1.56,0.64,1);
    pointer-events:none;
    filter:
        drop-shadow(0 10px 22px rgba(0,0,0,0.55))
        drop-shadow(0 0 16px rgba(200,255,245,0.55));
}
body.torch-on .robot-torch{ opacity:1; transform:translate(0,0) scale(1); }
body.torch-on.robot-touched .robot-torch{ transform:translate(4px,4px) scale(1); }
.torch-aim{
    width:100%;height:100%;
    transform-origin:50% 100%;
    transform: rotate(-14deg);
    will-change: transform;
    transition: none;
}
.torch-aim svg{ width:100%; height:100%; display:block; overflow:visible; }

/* =========================================================
   THOUGHT BUBBLE
   ========================================================= */
.thought-bubble{
    position:absolute;
    bottom:30%;right:52%;
    max-width:62%;
    z-index:6;
    padding:0.85rem 1.25rem;
    font-family:'Poppins','Inter',sans-serif;
    font-size:0.85rem;
    font-weight:700;
    line-height:1.35;
    color:#0a1620;
    border-radius: 50px 68px 55px 62px / 62px 50px 68px 55px;
    background:
        radial-gradient(circle at 18% 24%, #ffffff 0%, rgba(255,255,255,0.9) 12%, transparent 28%),
        radial-gradient(circle at 82% 22%, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0.5) 10%, transparent 22%),
        radial-gradient(circle at 50% 92%, rgba(190,220,220,0.22) 0%, transparent 40%),
        radial-gradient(ellipse at 40% 35%, #ffffff 0%, #fbfefd 35%, #e9f8f4 70%, #cfe9e2 100%);
    box-shadow:
        inset 10px 8px 20px rgba(255,255,255,1),
        inset 20px 12px 30px rgba(255,255,255,0.55),
        inset -14px -18px 28px rgba(150,195,205,0.35),
        0 20px 35px -8px rgba(0,0,0,0.42),
        0 0 40px rgba(255,255,255,0.6),
        0 0 90px rgba(94,234,212,0.5),
        0 0 0 1px rgba(255,255,255,0.85);
    opacity:0;
    transform:scale(0.35) translateY(18px);
    transform-origin:bottom right;
    pointer-events:none;overflow-wrap:anywhere;
    text-shadow:0 1px 0 rgba(255,255,255,0.6);
    transition:background 0.5s ease,box-shadow 0.5s ease;
}
.thought-bubble .accent{ color:#0ea5b7; }
.thought-bubble .accent-pink{ color:#ec4899; }
.thought-bubble .accent-red{ color:#dc2626; }

.thought-bubble.thought-red{
    background:
        radial-gradient(circle at 18% 24%, #ffffff 0%, rgba(255,255,255,0.9) 12%, transparent 28%),
        radial-gradient(circle at 82% 22%, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0.5) 10%, transparent 22%),
        radial-gradient(circle at 50% 92%, rgba(220,60,60,0.30) 0%, transparent 40%),
        radial-gradient(ellipse at 40% 35%, #fff0f0 0%, #fecaca 35%, #fca5a5 70%, #f87171 100%);
    box-shadow:
        inset 10px 8px 20px rgba(255,255,255,1),
        inset 20px 12px 30px rgba(255,255,255,0.55),
        inset -14px -18px 28px rgba(180,60,60,0.4),
        0 20px 35px -8px rgba(0,0,0,0.42),
        0 0 40px rgba(255,160,160,0.7),
        0 0 90px rgba(220,38,38,0.6),
        0 0 0 1px rgba(255,255,255,0.9);
    color:#4c0519;
}
.thought-bubble.thought-red::before,
.thought-bubble.thought-red::after{
    background:radial-gradient(circle at 35% 30%, #ffffff 0%, #fecaca 55%, #fca5a5 100%);
    box-shadow:
        0 0 16px rgba(255,255,255,0.7),
        0 0 26px rgba(220,38,38,0.6),
        inset -2px -2px 4px rgba(180,60,60,0.4),
        inset 2px 2px 4px rgba(255,255,255,1);
}
.thought-bubble::before,
.thought-bubble::after{
    content:"";position:absolute;
    background:radial-gradient(circle at 35% 30%, #ffffff 0%, #f1fbf9 55%, #cfe9e3 100%);
    border-radius:50%;
    box-shadow:
        0 0 16px rgba(255,255,255,0.7),
        0 0 26px rgba(94,234,212,0.5),
        inset -2px -2px 4px rgba(150,195,205,0.35),
        inset 2px 2px 4px rgba(255,255,255,1);
    opacity:0;transform:scale(0.3);
}
.thought-bubble::before{ bottom:-14px; right:22px; width:14px; height:14px; }
.thought-bubble::after { bottom:-28px; right:8px;  width:8px;  height:8px;  }

body.thought-show .thought-bubble{ animation: thoughtIn 0.7s cubic-bezier(0.34,1.56,0.64,1) forwards; }
body.thought-show .thought-bubble::before{ animation: dotIn 0.5s cubic-bezier(0.34,1.56,0.64,1) 0.25s forwards; }
body.thought-show .thought-bubble::after { animation: dotIn 0.5s cubic-bezier(0.34,1.56,0.64,1) 0.4s forwards; }
@keyframes thoughtIn{
    0%  { opacity:0; transform:scale(0.35) translateY(18px); }
    60% { opacity:1; transform:scale(1.06) translateY(-2px); }
    100%{ opacity:1; transform:scale(1) translateY(0); }
}
@keyframes dotIn{ 0%{opacity:0;transform:scale(0.3);} 100%{opacity:1;transform:scale(1);} }
body.thought-hide .thought-bubble{ animation: thoughtOut 0.55s ease-in forwards; }
body.thought-hide .thought-bubble::before,
body.thought-hide .thought-bubble::after{ animation: dotOut 0.4s ease-in forwards; }
@keyframes thoughtOut{
    0%  { opacity:1; transform:scale(1) translateY(0); filter:blur(0); }
    100%{ opacity:0; transform:scale(1.12) translateY(-14px); filter:blur(6px); }
}
@keyframes dotOut{
    0%  { opacity:1; transform:scale(1); }
    100%{ opacity:0; transform:scale(0.3) translateY(-6px); }
}

/* =========================================================
   LOGIN PANEL
   ========================================================= */
.login-box{
    position:relative;width:100%;perspective:900px;
    opacity:0;transform:translateY(30px);
    animation:panelIn 1.0s cubic-bezier(0.23,1,0.32,1) 2.6s forwards;
}
@keyframes panelIn{ to{opacity:1;transform:translateY(0);} }

.login-box > form{
    position:relative;
    background:linear-gradient(155deg,rgba(14,22,32,0.72),rgba(6,10,16,0.85));
    backdrop-filter:blur(22px) saturate(140%);
    -webkit-backdrop-filter:blur(22px) saturate(140%);
    border:1.5px solid rgba(120,200,220,0.28);
    border-radius:26px;
    padding:2rem 1.7rem;
    box-shadow:
        0 30px 80px -20px rgba(0,0,0,0.75),
        0 0 0 1px rgba(120,200,220,0.08),
        inset 0 1px 0 rgba(180,255,240,0.12);
    transition:border-color 0.5s ease,box-shadow 0.5s ease,background 0.5s ease,transform 0.5s ease;
    backface-visibility:hidden;transform-style:preserve-3d;overflow:hidden;
}
.login-box > form::before{
    content:"";position:absolute;inset:-1.5px;border-radius:26px;pointer-events:none;
    background:
        radial-gradient(ellipse at 12% 0%,
            rgba(94,234,212,0.42) 0%, rgba(94,234,212,0.14) 30%,
            rgba(94,234,212,0.04) 55%, transparent 75%);
    opacity:0;
    animation: cardTopLight 2.6s cubic-bezier(0.23,1,0.32,1) 2.8s forwards;
    z-index:0;transition:background 0.5s ease;
}
@keyframes cardTopLight{
    0%   { opacity:0;   filter:brightness(0.4) blur(2px); transform:scale(0.96); }
    50%  { opacity:0.7; filter:brightness(0.8) blur(1px); }
    100% { opacity:1;   filter:brightness(1) blur(0);     transform:scale(1); }
}
.login-box > form::after{
    content:"";position:absolute;inset:-1.5px;border-radius:26px;pointer-events:none;
    background:
        radial-gradient(ellipse at 88% 100%,
            rgba(34,211,238,0.30) 0%, rgba(34,211,238,0.10) 35%, transparent 65%);
    opacity:0;
    animation: cardBottomLight 2.2s cubic-bezier(0.23,1,0.32,1) 3.6s forwards;
    z-index:0;transition:background 0.5s ease;
}
@keyframes cardBottomLight{
    0%   { opacity:0;   filter:brightness(0.4); }
    100% { opacity:1;   filter:brightness(1); }
}
.login-box > form > *{ position:relative; z-index:1; }

body.login-success .login-box > form{
    border-color:rgba(34,197,94,0.9);
    background:linear-gradient(155deg,rgba(14,32,22,0.78),rgba(4,16,10,0.9));
    box-shadow:
        0 30px 80px -20px rgba(0,0,0,0.75),
        0 0 0 1px rgba(34,197,94,0.25),
        0 0 44px rgba(34,197,94,0.55),
        0 0 90px rgba(34,197,94,0.30),
        inset 0 1px 0 rgba(190,255,210,0.22);
    animation: formShakeSuccess 0.55s cubic-bezier(0.36,0.07,0.19,0.97);
}
body.login-success .login-box > form::before{
    background:radial-gradient(ellipse at 12% 0%, rgba(34,197,94,0.55) 0%, rgba(34,197,94,0.18) 30%, rgba(34,197,94,0.05) 55%, transparent 75%);
}
body.login-success .login-box > form::after{
    background:radial-gradient(ellipse at 88% 100%, rgba(74,222,128,0.45) 0%, rgba(74,222,128,0.14) 35%, transparent 65%);
}
body.login-success .form-control{ border-color:rgba(34,197,94,0.55) !important; }
body.login-success .btn-primary{
    background:linear-gradient(135deg,#22c55e 0%,#16a34a 55%,#15803d 100%);
    color:#04120a;
    box-shadow:0 14px 40px rgba(34,197,94,0.5),inset 0 1px 0 rgba(255,255,255,0.4);
}
@keyframes formShakeSuccess{
    0%, 100% { translate: 0 0; }
    15%      { translate: -6px 1px; }
    30%      { translate: 6px -1px; }
    45%      { translate: -5px 0; }
    60%      { translate: 4px 0; }
    75%      { translate: -3px 0; }
    90%      { translate: 2px 0; }
}

body.login-error .login-box > form{
    border-color:rgba(249,115,22,0.9);
    background:linear-gradient(155deg,rgba(38,22,10,0.82),rgba(20,10,4,0.92));
    box-shadow:
        0 30px 80px -20px rgba(0,0,0,0.75),
        0 0 0 1px rgba(249,115,22,0.28),
        0 0 48px rgba(249,115,22,0.6),
        0 0 100px rgba(249,115,22,0.32),
        inset 0 1px 0 rgba(255,220,180,0.22);
    animation: formShakeError 0.75s cubic-bezier(0.36,0.07,0.19,0.97);
}
body.login-error .login-box > form::before{
    background:radial-gradient(ellipse at 12% 0%, rgba(249,115,22,0.55) 0%, rgba(249,115,22,0.18) 30%, rgba(249,115,22,0.05) 55%, transparent 75%);
}
body.login-error .login-box > form::after{
    background:radial-gradient(ellipse at 88% 100%, rgba(251,146,60,0.45) 0%, rgba(251,146,60,0.14) 35%, transparent 65%);
}
body.login-error .form-control{ border-color:rgba(249,115,22,0.55) !important; }
body.login-error .btn-primary{
    background:linear-gradient(135deg,#f97316 0%,#ea580c 55%,#c2410c 100%);
    color:#1a0c04;
    box-shadow:0 14px 40px rgba(249,115,22,0.5),inset 0 1px 0 rgba(255,255,255,0.4);
}
body.login-error .toggle-pw{ color:#fdba74; }
body.login-error .form-label{ color:#fdba74; }
body.login-error .utility a{ color:#fdba74 !important; }
body.login-error .utility a:hover{ color:#fed7aa !important; text-shadow:0 0 14px rgba(249,115,22,0.6); }
@keyframes formShakeError{
    0%, 100% { translate: 0 0; }
    8%       { translate: -12px 2px; }
    16%      { translate: 12px -2px; }
    24%      { translate: -11px 1px; }
    32%      { translate: 11px -1px; }
    40%      { translate: -9px 0; }
    50%      { translate: 9px 0; }
    60%      { translate: -7px 0; }
    72%      { translate: 6px 0; }
    84%      { translate: -4px 0; }
    92%      { translate: 3px 0; }
}

.login-box .login-form  { transform:rotateY(0deg); }
.login-box .forget-form {
    position:absolute;top:0;left:0;width:100%;
    transform:rotateY(180deg);opacity:0;pointer-events:none;
}
.login-box.flipped .login-form  { transform:rotateY(-180deg);opacity:0;pointer-events:none; }
.login-box.flipped .forget-form { transform:rotateY(0deg);opacity:1;pointer-events:auto; }

.login-box img.brand-logo{
    display:block;margin:0 auto 0.55rem;max-height:62px;width:auto;
    filter:drop-shadow(0 6px 22px rgba(34,211,238,0.35));
}

/* =========================================================
   WELCOME PILL
   ========================================================= */
.welcome-slot{
    display:flex;justify-content:center;
    margin:0.3rem 0 0.4rem;min-height:46px;position:relative;
}
.welcome-pill{
    position:relative;display:inline-block;
    padding:0.5rem 1.4rem;
    font-family:'Poppins','Inter',sans-serif;
    font-size:1.2rem;font-weight:800;letter-spacing:-0.01em;
    color:#ffffff;
    background:
        radial-gradient(ellipse at 30% 25%, rgba(255,255,255,0.28) 0%, rgba(255,255,255,0.08) 55%, rgba(255,255,255,0.02) 100%);
    border:1.5px solid rgba(255,255,255,0.55);
    border-radius:60px;
    box-shadow:
        0 0 0 1px rgba(94,234,212,0.18),
        0 0 28px rgba(255,255,255,0.35),
        0 0 60px rgba(94,234,212,0.28),
        inset 0 1px 0 rgba(255,255,255,0.65),
        inset 0 -8px 20px rgba(94,234,212,0.15);
    backdrop-filter:blur(14px) saturate(180%);
    -webkit-backdrop-filter:blur(14px) saturate(180%);
    opacity:0;transform:scale(0.3);transition:none;overflow:hidden;
    text-shadow:0 0 18px rgba(255,255,255,0.55), 0 0 8px rgba(255,255,255,0.4);
}
.welcome-pill .accent{ color:#7ff3df; text-shadow:0 0 20px rgba(127,243,223,0.75); }
.welcome-pill::before{
    content:"";position:absolute;inset:0;border-radius:60px;
    background:linear-gradient(115deg,
        transparent 30%,
        rgba(255,255,255,0.55) 45%,
        rgba(180,255,240,0.35) 50%,
        rgba(255,255,255,0.55) 55%,
        transparent 70%);
    transform:translateX(-120%);pointer-events:none;mix-blend-mode:screen;
}
.welcome-pill::after{
    content:"";position:absolute;inset:-2px;border-radius:60px;
    background:
        radial-gradient(circle at 12% 25%, rgba(255,255,255,0.9) 0 1.5px, transparent 3px),
        radial-gradient(circle at 85% 70%, rgba(180,255,240,0.9) 0 1.5px, transparent 3px),
        radial-gradient(circle at 55% 8%, rgba(255,255,255,0.8) 0 1px, transparent 2px);
    pointer-events:none;opacity:0.85;
}

body.welcome-in .welcome-pill{ animation: pillPop 0.85s cubic-bezier(0.34,1.56,0.64,1) forwards; }
body.welcome-in .welcome-pill::before{ animation: pillSheen 1.6s cubic-bezier(0.23,1,0.32,1) 0.4s 1 forwards; }
@keyframes pillPop{
    0%  { opacity:0; transform:scale(0.3); filter:blur(6px); }
    55% { opacity:1; transform:scale(1.08); filter:blur(0); }
    100%{ opacity:1; transform:scale(1); filter:blur(0); }
}
@keyframes pillSheen{ 0%{transform:translateX(-120%);} 100%{transform:translateX(120%);} }

.welcome-slot::after{
    content:"";position:absolute;width:22px;height:22px;
    border-radius:50%;border:2px solid rgba(255,255,255,0.7);
    opacity:0;pointer-events:none;
}
body.welcome-in .welcome-slot::after{ animation: ringBurst 1.1s cubic-bezier(0.23,1,0.32,1) forwards; }
@keyframes ringBurst{
    0%  { opacity:0.9; transform:scale(0.4); }
    100%{ opacity:0;   transform:scale(6); }
}
.welcome-pill.swap{ animation: pillSwap 0.75s cubic-bezier(0.34,1.56,0.64,1) forwards; }
.welcome-pill.pill-success{
    border-color:rgba(190,255,215,0.9);
    background:
        radial-gradient(ellipse at 30% 25%, rgba(255,255,255,0.32) 0%, rgba(255,255,255,0.10) 55%, rgba(255,255,255,0.02) 100%),
        linear-gradient(135deg,rgba(34,197,94,0.35),rgba(22,163,74,0.25));
    box-shadow:
        0 0 0 1px rgba(34,197,94,0.5),
        0 0 44px rgba(255,255,255,0.6),
        0 0 90px rgba(34,197,94,0.65),
        inset 0 1px 0 rgba(255,255,255,0.8),
        inset 0 -8px 22px rgba(34,197,94,0.35);
    color:#eaffef;
    text-shadow:0 0 20px rgba(190,255,210,0.85), 0 0 8px rgba(255,255,255,0.6);
}
.welcome-pill.pill-error{
    border-color:rgba(255,220,180,0.9);
    background:
        radial-gradient(ellipse at 30% 25%, rgba(255,255,255,0.30) 0%, rgba(255,255,255,0.08) 55%, rgba(255,255,255,0.02) 100%),
        linear-gradient(135deg,rgba(249,115,22,0.38),rgba(234,88,12,0.25));
    box-shadow:
        0 0 0 1px rgba(249,115,22,0.5),
        0 0 44px rgba(255,255,255,0.55),
        0 0 90px rgba(249,115,22,0.6),
        inset 0 1px 0 rgba(255,255,255,0.8),
        inset 0 -8px 22px rgba(249,115,22,0.35);
    color:#fff4e5;
    text-shadow:0 0 20px rgba(255,220,180,0.85), 0 0 8px rgba(255,255,255,0.55);
}
@keyframes pillSwap{
    0%  { transform:scale(1); }
    25% { transform:scale(0.82); opacity:0.4; filter:blur(4px); }
    60% { transform:scale(1.14); opacity:1; filter:blur(0); }
    100%{ transform:scale(1);   opacity:1; filter:blur(0); }
}

/* =========================================================
   FORM FIELDS + BUTTON
   ========================================================= */
.form-label{
    color:#9edbd2;font-weight:600;font-size:0.68rem;
    letter-spacing:0.14em;text-transform:uppercase;
    margin-bottom:0.35rem;display:block;
    transition:color 0.4s ease;
}
.field-wrap{position:relative;}
.form-control{
    width:100%;
    background:rgba(6,14,22,0.65);
    border:1px solid rgba(120,200,220,0.22);
    border-radius:13px;
    padding:0.75rem 2.4rem 0.75rem 1rem;
    color:#e6f7ff !important;font-size:0.92rem;
    transition:all 0.35s ease;outline:none;
    box-shadow:inset 0 1px 0 rgba(255,255,255,0.03);
}
.form-control::placeholder{color:#5b7d88;}
.form-control:hover{border-color:rgba(120,200,220,0.4);}
.form-control:focus{
    background:rgba(6,20,28,0.85);
    border-color:#5eead4;
    box-shadow:0 0 0 4px rgba(94,234,212,0.15),0 0 24px rgba(94,234,212,0.35);
    color:#f2feff !important;
}
.toggle-pw{
    position:absolute;right:10px;top:50%;
    transform:translateY(-50%);
    background:none;border:none;color:#5eead4;
    cursor:pointer;padding:6px;font-size:1rem;line-height:1;
    opacity:0.75;transition:opacity 0.2s,color 0.2s;
}
.toggle-pw:hover{opacity:1;color:#a7fff0;}

.utility a{
    color:#93b8c4 !important;text-decoration:none;
    font-weight:500;font-size:0.82rem;transition:color 0.3s;
}
.utility a:hover{color:#5eead4 !important;text-shadow:0 0 14px rgba(94,234,212,0.5);}

.btn-primary{
    background:linear-gradient(135deg,#2dd4bf 0%,#0ea5b7 55%,#0b7285 100%);
    border:none;border-radius:50px;padding:0.8rem 1.3rem;
    font-weight:700;letter-spacing:0.06em;color:#05161a;
    box-shadow:0 12px 32px rgba(45,212,191,0.32),inset 0 1px 0 rgba(255,255,255,0.35);
    transition:all 0.28s ease;width:100%;font-size:0.9rem;
    position:relative;overflow:hidden;
}
.btn-primary:hover{
    transform:translateY(-2px);
    box-shadow:0 18px 42px rgba(45,212,191,0.5),inset 0 1px 0 rgba(255,255,255,0.4);
}
.btn-primary:active{transform:translateY(0);}
.btn-primary[disabled]{opacity:0.85;cursor:not-allowed;}

.btn-envelope{
    display:flex !important;
    align-items:center;justify-content:center;
    min-height:2.8rem;
    padding:0.55rem 1.3rem !important;
}
.btn-envelope .envelope-svg{
    width:58px;height:36px;display:block;
    transition: transform 0.7s cubic-bezier(0.5, 0, 0.4, 1), opacity 0.6s ease;
    overflow:visible;
}
.btn-envelope.env-depart .envelope-svg{
    transform:translateX(220%) scale(0.35) rotate(20deg);
    opacity:0;
}
.btn-envelope .env-flap{
    transform-box:fill-box;transform-origin:top center;
    transition:transform 0.55s cubic-bezier(0.4, 0, 0.2, 1);
}
.btn-envelope.env-open .env-flap{ transform:rotateX(-180deg); }
.btn-envelope .env-credentials circle{
    opacity:0; transform:translateY(-40px) scale(0.4);
    transform-box:fill-box;
}
.btn-envelope.env-fill .env-credentials circle{
    animation:credDrop 0.55s cubic-bezier(0.34,1.2,0.64,1) forwards;
}
.btn-envelope.env-fill .env-credentials circle:nth-child(1){ animation-delay:0s; }
.btn-envelope.env-fill .env-credentials circle:nth-child(2){ animation-delay:0.10s; }
.btn-envelope.env-fill .env-credentials circle:nth-child(3){ animation-delay:0.20s; }
@keyframes credDrop{
    0%   { opacity:0; transform:translateY(-40px) scale(0.4); }
    60%  { opacity:1; transform:translateY(2px) scale(1.05); }
    100% { opacity:1; transform:translateY(0) scale(1); }
}
.btn-envelope .env-label{
    margin-left:0.6rem;font-weight:700;letter-spacing:0.06em;font-size:0.85rem;
}

.stage{opacity:0;transform:translateY(18px);animation:stageIn 0.7s cubic-bezier(0.23,1,0.32,1) forwards;}
@keyframes stageIn{to{opacity:1;transform:translateY(0);}}
.d-1{animation-delay:2.75s;}
.d-2{animation-delay:2.90s;}
.d-3{animation-delay:3.05s;}
.d-4{animation-delay:3.20s;}
.d-5{animation-delay:3.35s;}
.d-6{animation-delay:3.50s;}

.inline-error{
    margin-top:0.6rem;padding:0.6rem 0.85rem;
    font-size:0.82rem;color:#ffe0c2;
    background:rgba(249,115,22,0.14);
    border:1px solid rgba(249,115,22,0.4);
    border-radius:12px;
    animation:errorIn 0.35s ease;display:none;
}
.inline-error.show{display:block;}
@keyframes errorIn{
    from{ opacity:0;transform:translateY(-4px); }
    to  { opacity:1;transform:translateY(0); }
}

/* =========================================================
   REAL FIREWORKS
   ========================================================= */
.fw-layer{position:fixed;inset:0;pointer-events:none;z-index:9999;overflow:hidden;}
.fw-rocket{
    position:absolute;width:8px;height:8px;margin:-4px 0 0 -4px;border-radius:50%;
    background:radial-gradient(circle, #ffffff 0%, #fff3c4 40%, #fbbf24 70%, rgba(251,191,36,0) 100%);
    box-shadow: 0 0 10px #ffffff, 0 0 20px #fde68a, 0 0 34px #f59e0b, 0 0 60px rgba(245,158,11,0.7);
    opacity:0;pointer-events:none;
    animation: fwRocketArc var(--dur, 0.9s) cubic-bezier(0.22, 1, 0.36, 1) var(--delay, 0s) forwards;
    will-change: transform, opacity;
}
@keyframes fwRocketArc{
    0%   { opacity:0; transform: translate(0,0) scale(0.4); }
    10%  { opacity:1; transform: translate(calc(var(--rx)*0.05), calc(var(--ry)*0.05)) scale(1); }
    80%  { opacity:1; transform: translate(calc(var(--rx)*0.95), calc(var(--ry)*0.95)) scale(0.9); }
    100% { opacity:0.7; transform: translate(var(--rx), var(--ry)) scale(0.6); }
}
.fw-trail{
    position:absolute;width:5px;height:5px;margin:-2.5px 0 0 -2.5px;border-radius:50%;
    background:radial-gradient(circle, #ffffff 0%, #fde68a 50%, rgba(245,158,11,0) 100%);
    box-shadow: 0 0 6px #ffffff, 0 0 14px rgba(251,191,36,0.9);
    pointer-events:none;opacity:0;
    animation: fwTrail var(--dur, 0.5s) ease-out var(--delay, 0s) forwards;
}
@keyframes fwTrail{
    0%   { opacity:0.95; transform: scale(0.9); }
    100% { opacity:0;    transform: scale(0.2); }
}
.fw-burst{position:absolute;width:1px;height:1px;pointer-events:none;}
.fw-flash{
    position:absolute;left:50%;top:50%;width:90px;height:90px;margin:-45px 0 0 -45px;border-radius:50%;
    background: radial-gradient(circle,
        rgba(255,255,255,0.95) 0%, rgba(255,240,180,0.6) 30%,
        rgba(251,191,36,0.25) 55%, transparent 80%);
    mix-blend-mode:screen;opacity:0;
    animation: fwFlash 0.55s ease-out forwards;
}
@keyframes fwFlash{
    0%   { opacity:0;   transform: scale(0.4); }
    25%  { opacity:1;   transform: scale(1); }
    100% { opacity:0;   transform: scale(2.4); }
}
.fw-spark{
    position:absolute;width:6px;height:6px;margin:-3px 0 0 -3px;border-radius:50%;
    background: radial-gradient(circle,
        #ffffff 0%, var(--c1, #ffe08a) 45%, var(--c2, #f59e0b) 75%, transparent 100%);
    box-shadow:
        0 0 8px #ffffff, 0 0 16px var(--c1, #ffe08a), 0 0 28px var(--c2, #f59e0b);
    pointer-events:none;opacity:0;will-change: transform, opacity;
    animation: fwSparkArc var(--dur, 1.35s) cubic-bezier(0.15, 0.55, 0.35, 1) var(--delay, 0s) forwards;
}
@keyframes fwSparkArc{
    0%   { opacity:1;  transform: translate(0,0) scale(0.4); }
    15%  { opacity:1;  transform: translate(calc(var(--fx)*0.25), calc(var(--fy)*0.25)) scale(1); }
    55%  { opacity:0.95; transform: translate(calc(var(--fx)*0.85), calc(var(--fy)*0.85 - 4px)) scale(0.95); }
    100% { opacity:0;  transform: translate(var(--fx), calc(var(--fy) + 46px)) scale(0.3); }
}
.fw-crackle{
    position:absolute;width:3px;height:3px;margin:-1.5px 0 0 -1.5px;border-radius:50%;
    background: radial-gradient(circle, #ffffff 0%, #fff3c4 50%, transparent 100%);
    box-shadow: 0 0 8px #ffffff, 0 0 14px #fde68a;
    pointer-events:none;opacity:0;
    animation: fwCrackle 0.6s ease-out var(--delay, 0s) forwards;
}
@keyframes fwCrackle{
    0%   { opacity:0;   transform: scale(0.3); }
    40%  { opacity:1;   transform: scale(1.2); }
    100% { opacity:0;   transform: scale(0.4) translate(0,4px); }
}

/* =========================================================
   BELL + TOAST
   ========================================================= */
.notif-bell{
    position:fixed;bottom:24px;right:24px;z-index:9999;
    background:rgba(12,20,30,0.85);backdrop-filter:blur(10px);
    border-radius:60px;padding:0.7rem 1rem;
    box-shadow:0 8px 30px rgba(0,0,0,0.5),0 0 0 1px rgba(120,200,220,0.25);
    display:flex;align-items:center;gap:0.5rem;cursor:pointer;
    color:#e6f7ff;text-decoration:none;transition:transform 0.2s;
}
.notif-bell:hover{transform:scale(1.05);}
.notif-bell .badge{
    background:#ef476f;color:white;border-radius:40px;
    padding:0.2rem 0.6rem;font-size:0.7rem;font-weight:700;
}
.notif-bell i{font-size:1.4rem;color:#5eead4;}
.notif-toast{
    position:fixed;bottom:80px;right:24px;max-width:380px;z-index:10000;
    background:rgba(10,18,26,0.95);backdrop-filter:blur(14px);
    border-radius:20px;padding:1.2rem 1.5rem;
    box-shadow:0 12px 40px rgba(0,0,0,0.5);
    border-left:6px solid #5eead4;display:none;
    animation:slideIn 0.4s cubic-bezier(0.23,1,0.32,1);color:#e6f7ff;
}
.notif-toast.show{display:block;}
@keyframes slideIn{from{transform:translateX(40px);opacity:0;}to{transform:translateX(0);opacity:1;}}
.notif-toast .toast-title{font-weight:700;font-size:1rem;display:flex;align-items:center;gap:0.5rem;color:#eaf9ff;}
.notif-toast .toast-title i{color:#5eead4;}
.notif-toast .toast-body{font-size:0.9rem;margin:0.3rem 0 0.5rem;color:#a8c6d0;}
.notif-toast .toast-actions{display:flex;gap:1rem;margin-top:0.5rem;}
.notif-toast .toast-actions a{color:#5eead4;font-weight:600;text-decoration:none;font-size:0.85rem;}
.notif-toast .toast-actions a:hover{text-decoration:underline;}
.notif-toast .btn-close-toast{background:none;border:none;font-size:1.2rem;cursor:pointer;color:#6f97a4;float:right;}
.notif-toast .btn-close-toast:hover{color:#e6f7ff;}

/* =========================================================
   RESPONSIVE
   ========================================================= */
@media (max-width:900px){
    .scene-container{
        flex-direction:column;gap:0.85rem;padding:0.85rem;
        justify-content:flex-start;padding-top:0.5rem;padding-bottom:1.6rem;
    }
    .visual-scene{width:100%;max-width:270px;height:320px;}
    .lamp-assembly{top:-4px;}
    .light-cone{top:120px;height:220px;}
    .robot-wrapper{width:52%;bottom:-2%;}
    .robot-torch{ width:48px; bottom:8%; right:-10%; }
    .robot-broom{ width:44px; right:-10%; }
    .robot-duster{ width:38px; right:-12%; }
    .thought-bubble{ bottom:36%;right:52%;max-width:66%; font-size:0.74rem;padding:0.65rem 0.95rem; }
    .thought-bubble::before{ bottom:-12px; right:18px; width:12px; height:12px; }
    .thought-bubble::after { bottom:-24px; right:6px;  width:7px;  height:7px;  }
    .form-scene{ max-width:100%; margin-top:1.6rem; }
    .login-box > form{ padding:2rem 1.4rem; border-radius:26px; }
    .login-box img.brand-logo{ max-height:110px; margin-bottom:0.75rem; }
    .welcome-pill{font-size:1.15rem;padding:0.5rem 1.35rem;}
    .login-head{font-size:1.3rem;}
    .notif-toast{right:12px;left:12px;max-width:none;bottom:70px;}
}
@media (max-width:480px){
    .login-box > form{ padding:1.8rem 1.2rem; border-radius:22px; }
    .login-box img.brand-logo{ max-height:100px; }
    .welcome-pill{font-size:1.05rem;padding:0.5rem 1.2rem;}
    .form-control{padding:0.8rem 2.4rem 0.8rem 1rem;font-size:0.94rem;}
    .btn-primary{padding:0.85rem 1.3rem;font-size:0.92rem;}
    .thought-bubble{font-size:0.72rem;}
    .form-label{font-size:0.7rem;}
}

/* =========================================================
   REDUCED MOTION
   ========================================================= */
@media (prefers-reduced-motion: reduce){
    .lamp-assembly,.lamp-bulb,.light-cone,.robot-wrapper,
    .robot-floater,.login-box,.stage,.bubble{
        animation:none !important; opacity:1 !important;
        transform:none !important; transition:none !important;
    }
    .thought-bubble{ display:none !important; }
    .welcome-pill{ opacity:1 !important;transform:none !important;animation:none !important; }
    .welcome-pill::before{ display:none; }
    .login-box > form::before, .login-box > form::after{
        animation:none !important; opacity:1 !important;
        filter:none !important; transform:none !important;
    }
    body.login-success .login-box > form,
    body.login-error   .login-box > form{ animation:none !important; }
    body.robot-glasses svg.robot,
    body.robot-reset   svg.robot{ animation:none !important; }
    body.robot-walking .robot-wrapper,
    body.robot-walking .robot-floater,
    body.chore-sweep .robot-floater,
    body.chore-sweep .robot-broom,
    body.chore-dust .robot-duster{ animation:none !important; transform:none !important; }
    .cursor-light,.cursor-reveal{ display:none !important; }
    .sunglasses{ transition:none !important; }
    .btn-envelope .envelope-svg, .btn-envelope .env-flap{ transition:none !important; }
    .fw-layer{ display:none !important; }
    .dust-puff{ display:none !important; }
}
</style>
</head>
<body>
<main>

    <div class="video-wrap">
        <video autoplay loop muted playsinline>
            <source src="videos/1.mp4" type="video/mp4">
        </video>
    </div>
    <canvas id="shader-canvas"></canvas>
    <canvas id="stars-canvas"></canvas>
    <div class="bubbles-layer" id="bubblesLayer"></div>

    <div class="cursor-reveal" id="cursorReveal" aria-hidden="true"></div>
    <div class="cursor-light"  id="cursorLight"  aria-hidden="true"></div>

    <section class="scene-container">

        <div class="visual-scene" id="visualScene" aria-hidden="true">

            <div class="lamp-assembly">
                <div class="lamp-cable"></div>
                <div class="lamp-fixture">
                    <div class="lamp-shade">
                        <div class="lamp-bulb"></div>
                    </div>
                </div>
            </div>

            <div class="light-cone"></div>

            <div class="thought-bubble" id="thoughtBubble">
                Welcome <span class="accent">Back.</span>
            </div>

            <!-- Robot stage: wide horizontal lane the robot can walk along -->
            <div class="robot-stage" id="robotStage">
                <div class="robot-wrapper" id="robotWrapper">
                    <div class="robot-glow-floor"></div>
                    <div class="robot-floater">

                        <svg class="robot" id="robot" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                            <defs>
                                <radialGradient id="headSphere" cx="34%" cy="28%" r="78%">
                                    <stop offset="0%"   stop-color="#3b5566"/>
                                    <stop offset="30%"  stop-color="#1c2b38"/>
                                    <stop offset="65%"  stop-color="#0b141d"/>
                                    <stop offset="100%" stop-color="#03080d"/>
                                </radialGradient>
                                <radialGradient id="headHighlight" cx="30%" cy="22%" r="45%">
                                    <stop offset="0%"   stop-color="rgba(255,255,255,0.55)"/>
                                    <stop offset="55%"  stop-color="rgba(200,255,245,0.18)"/>
                                    <stop offset="100%" stop-color="rgba(255,255,255,0)"/>
                                </radialGradient>
                                <radialGradient id="headBounce" cx="76%" cy="82%" r="50%">
                                    <stop offset="0%"   stop-color="rgba(94,234,212,0.35)"/>
                                    <stop offset="60%"  stop-color="rgba(94,234,212,0.08)"/>
                                    <stop offset="100%" stop-color="rgba(94,234,212,0)"/>
                                </radialGradient>
                                <radialGradient id="facePlate" cx="50%" cy="55%" r="60%">
                                    <stop offset="0%"   stop-color="#02070b"/>
                                    <stop offset="100%" stop-color="#0a1720"/>
                                </radialGradient>
                                <radialGradient id="eyeGrad" cx="40%" cy="35%" r="75%">
                                    <stop offset="0%"   stop-color="#ffffff"/>
                                    <stop offset="45%"  stop-color="#e6fbff"/>
                                    <stop offset="100%" stop-color="#7ad7e6"/>
                                </radialGradient>
                                <linearGradient id="rimStroke" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%"   stop-color="rgba(94,234,212,0.95)"/>
                                    <stop offset="55%"  stop-color="rgba(94,234,212,0.35)"/>
                                    <stop offset="100%" stop-color="rgba(34,211,238,0.95)"/>
                                </linearGradient>
                                <radialGradient id="earGrad" cx="40%" cy="35%" r="70%">
                                    <stop offset="0%"   stop-color="#1d3441"/>
                                    <stop offset="100%" stop-color="#050e16"/>
                                </radialGradient>
                                <radialGradient id="bulbGrad" cx="40%" cy="35%" r="70%">
                                    <stop offset="0%"   stop-color="#e6fff8"/>
                                    <stop offset="55%"  stop-color="#5eead4"/>
                                    <stop offset="100%" stop-color="#0c7f8f"/>
                                </radialGradient>
                                <linearGradient id="lensDark" x1="0%" y1="0%" x2="0%" y2="100%">
                                    <stop offset="0%" stop-color="#1a1a1a"/>
                                    <stop offset="55%" stop-color="#0a0a0a"/>
                                    <stop offset="100%" stop-color="#000000"/>
                                </linearGradient>
                                <filter id="softShadow" x="-40%" y="-40%" width="180%" height="180%">
                                    <feGaussianBlur stdDeviation="3"/>
                                </filter>
                            </defs>

                            <ellipse cx="100" cy="106" rx="86" ry="84" fill="rgba(0,0,0,0.55)" filter="url(#softShadow)"/>

                            <g>
                                <ellipse cx="18" cy="104" rx="10" ry="20" fill="url(#earGrad)"
                                         stroke="rgba(94,234,212,0.55)" stroke-width="1.2"/>
                                <ellipse cx="182" cy="104" rx="10" ry="20" fill="url(#earGrad)"
                                         stroke="rgba(94,234,212,0.55)" stroke-width="1.2"/>
                                <ellipse cx="18" cy="104" rx="3" ry="8" fill="#5eead4" opacity="0.75"/>
                                <ellipse cx="182" cy="104" rx="3" ry="8" fill="#5eead4" opacity="0.75"/>
                            </g>

                            <line x1="76" y1="40" x2="60" y2="18"
                                  stroke="#22d3ee" stroke-width="2.6" stroke-linecap="round"/>
                            <circle cx="60" cy="18" r="5.5" fill="url(#bulbGrad)">
                                <animate attributeName="opacity" values="0.75;1;0.75" dur="2.4s" repeatCount="indefinite"/>
                            </circle>
                            <line x1="124" y1="40" x2="140" y2="18"
                                  stroke="#22d3ee" stroke-width="2.6" stroke-linecap="round"/>
                            <circle cx="140" cy="18" r="5.5" fill="url(#bulbGrad)">
                                <animate attributeName="opacity" values="0.75;1;0.75" dur="2.4s" repeatCount="indefinite"/>
                            </circle>

                            <ellipse cx="100" cy="105" rx="80" ry="78" fill="url(#headSphere)"/>
                            <ellipse cx="100" cy="105" rx="80" ry="78" fill="url(#headHighlight)" pointer-events="none"/>
                            <ellipse cx="100" cy="105" rx="80" ry="78" fill="url(#headBounce)" pointer-events="none"/>
                            <ellipse cx="100" cy="105" rx="80" ry="78"
                                     fill="none" stroke="url(#rimStroke)" stroke-width="1.6" opacity="0.9"/>
                            <ellipse cx="100" cy="105" rx="79" ry="77"
                                     fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="0.6"/>
                            <ellipse cx="60" cy="60" rx="6" ry="4"
                                     fill="rgba(255,255,255,0.55)" transform="rotate(-25 60 60)"/>

                            <ellipse cx="100" cy="108" rx="60" ry="56"
                                     fill="url(#facePlate)" opacity="0.94"/>
                            <ellipse cx="100" cy="108" rx="60" ry="56"
                                     fill="none" stroke="rgba(94,234,212,0.35)" stroke-width="1"/>

                            <path class="eyebrow eyebrow-left"
                                  d="M 62 74 Q 74 68 88 72"
                                  stroke="#7ad7e6" stroke-width="2.4"
                                  fill="none" stroke-linecap="round"/>
                            <path class="eyebrow eyebrow-right"
                                  d="M 112 72 Q 126 68 138 74"
                                  stroke="#7ad7e6" stroke-width="2.4"
                                  fill="none" stroke-linecap="round"/>

                            <ellipse class="eye eye-left"  cx="80"  cy="98" rx="14" ry="17" fill="url(#eyeGrad)"/>
                            <ellipse class="eye eye-right" cx="120" cy="98" rx="14" ry="17" fill="url(#eyeGrad)"/>

                            <circle class="pupil pupil-left"  cx="80"  cy="98" r="6.5" fill="#04141b"/>
                            <circle class="pupil pupil-right" cx="120" cy="98" r="6.5" fill="#04141b"/>

                            <circle cx="77" cy="94" r="2" fill="#ffffff" opacity="0.9"/>
                            <circle cx="117" cy="94" r="2" fill="#ffffff" opacity="0.9"/>

                            <circle class="blush blush-left"  cx="56" cy="122" r="7" fill="rgba(255,140,170,0.75)"/>
                            <circle class="blush blush-right" cx="144" cy="122" r="7" fill="rgba(255,140,170,0.75)"/>

                            <g class="sunglasses" aria-hidden="true">
                                <rect x="50" y="76" width="54" height="38" rx="16" ry="16" fill="url(#lensDark)"/>
                                <rect x="96" y="76" width="54" height="38" rx="16" ry="16" fill="url(#lensDark)"/>
                                <rect x="50" y="76" width="54" height="38" rx="16" ry="16"
                                      fill="none" stroke="#000000" stroke-width="3"/>
                                <rect x="96" y="76" width="54" height="38" rx="16" ry="16"
                                      fill="none" stroke="#000000" stroke-width="3"/>
                                <rect x="96" y="88" width="8" height="8" fill="#000000"/>
                                <path d="M 58 86 Q 72 80 86 84"
                                      stroke="rgba(255,255,255,0.85)" stroke-width="2.4"
                                      fill="none" stroke-linecap="round"/>
                                <path d="M 104 86 Q 118 80 132 84"
                                      stroke="rgba(255,255,255,0.85)" stroke-width="2.4"
                                      fill="none" stroke-linecap="round"/>
                                <circle cx="72" cy="94" r="2" fill="rgba(255,255,255,0.9)"/>
                                <circle cx="118" cy="94" r="2" fill="rgba(255,255,255,0.9)"/>
                            </g>

                            <path class="mouth-happy" d="M 88 138 Q 100 148 112 138"
                                  stroke="#5eead4" stroke-width="2.4" fill="none" stroke-linecap="round"/>
                            <path class="mouth-sad"   d="M 88 146 Q 100 136 112 146"
                                  stroke="#5eead4" stroke-width="2.4" fill="none" stroke-linecap="round"/>
                            <path class="mouth-think" d="M 88 142 L 100 140 L 112 142"
                                  stroke="#5eead4" stroke-width="2.4" fill="none"
                                  stroke-linecap="round" stroke-linejoin="round"/>
                            <path class="mouth-scared" d="M 84 142 Q 92 136 100 142 Q 108 148 116 142"
                                  stroke="#7ad7e6" stroke-width="2.4" fill="none"
                                  stroke-linecap="round" stroke-linejoin="round"/>
                            <path class="mouth-sweet" d="M 92 140 Q 100 146 108 140"
                                  stroke="#ffb6c1" stroke-width="2.6" fill="none" stroke-linecap="round"/>
                            <circle class="mouth-o" cx="100" cy="143" r="3.5"
                                    fill="none" stroke="#5eead4" stroke-width="2.2"/>
                            <path class="mouth-laugh" d="M 84 136 Q 100 158 116 136 Z"
                                  fill="rgba(94,234,212,0.85)" stroke="#5eead4" stroke-width="2.2"
                                  stroke-linejoin="round"/>
                            <path class="mouth-focus" d="M 90 143 L 100 142 L 110 143"
                                  stroke="#5eead4" stroke-width="2.6" fill="none" stroke-linecap="round"/>
                            <ellipse class="mouth-yawn" cx="100" cy="144" rx="7" ry="9"
                                     fill="#04141b" stroke="#5eead4" stroke-width="2.2"/>
                            <path class="mouth-angry" d="M 88 148 L 100 140 L 112 148"
                                  stroke="#ef4444" stroke-width="2.6" fill="none"
                                  stroke-linecap="round" stroke-linejoin="round"/>
                            <path class="mouth-love" d="M 92 141 Q 100 149 108 141"
                                  stroke="#ec4899" stroke-width="2.8" fill="none" stroke-linecap="round"/>
                            <!-- Chore mouth: little tongue sticking out, focused -->
                            <path class="mouth-chore" d="M 90 142 Q 100 148 110 142"
                                  stroke="#5eead4" stroke-width="2.4" fill="none" stroke-linecap="round"/>
                            <path class="mouth-chore" d="M 100 146 Q 100 153 104 153 Q 106 150 100 146 Z"
                                  fill="#ff7f9d" stroke="#ff7f9d" stroke-width="1" stroke-linejoin="round"/>

                            <g class="heart heart-1" transform="translate(60, 30)">
                                <path d="M 0 4 C -4 -4, -12 -4, -12 4 C -12 10, 0 18, 0 18 C 0 18, 12 10, 12 4 C 12 -4, 4 -4, 0 4 Z"
                                      fill="#ec4899"/>
                            </g>
                            <g class="heart heart-2" transform="translate(140, 26)">
                                <path d="M 0 4 C -3 -3, -10 -3, -10 4 C -10 9, 0 16, 0 16 C 0 16, 10 9, 10 4 C 10 -3, 3 -3, 0 4 Z"
                                      fill="#f472b6"/>
                            </g>

                            <circle cx="52" cy="122" r="3.5" fill="#22d3ee" opacity="0.55" class="cheek"/>
                            <circle cx="148" cy="122" r="3.5" fill="#22d3ee" opacity="0.55" class="cheek"/>
                        </svg>

                        <!-- Torch -->
                        <div class="robot-torch" id="robotTorch" aria-hidden="true">
                            <div class="torch-aim" id="torchAim">
                                <svg viewBox="0 0 80 120" xmlns="http://www.w3.org/2000/svg">
                                    <defs>
                                        <linearGradient id="torchHandle" x1="0%" y1="0%" x2="0%" y2="100%">
                                            <stop offset="0%" stop-color="#1c2b38"/>
                                            <stop offset="100%" stop-color="#050e16"/>
                                        </linearGradient>
                                        <linearGradient id="torchHead" x1="0%" y1="0%" x2="0%" y2="100%">
                                            <stop offset="0%" stop-color="#2a3f4d"/>
                                            <stop offset="100%" stop-color="#0b141d"/>
                                        </linearGradient>
                                        <radialGradient id="torchLens" cx="50%" cy="50%" r="50%">
                                            <stop offset="0%" stop-color="#ffffff"/>
                                            <stop offset="55%" stop-color="#e8fdfa"/>
                                            <stop offset="100%" stop-color="#b8f0e6"/>
                                        </radialGradient>
                                    </defs>
                                    <rect x="30" y="42" width="20" height="70" rx="7"
                                          fill="url(#torchHandle)" stroke="#22d3ee" stroke-width="1.4"/>
                                    <rect x="30" y="42" width="20" height="70" rx="7"
                                          fill="none" stroke="rgba(94,234,212,0.35)" stroke-width="0.6"/>
                                    <line x1="32" y1="62" x2="48" y2="62" stroke="rgba(94,234,212,0.45)" stroke-width="1.2"/>
                                    <line x1="32" y1="72" x2="48" y2="72" stroke="rgba(94,234,212,0.45)" stroke-width="1.2"/>
                                    <line x1="32" y1="82" x2="48" y2="82" stroke="rgba(94,234,212,0.45)" stroke-width="1.2"/>
                                    <path d="M 12 42 L 68 42 L 60 12 L 20 12 Z"
                                          fill="url(#torchHead)" stroke="#5eead4" stroke-width="1.6" stroke-linejoin="round"/>
                                    <ellipse cx="40" cy="12" rx="28" ry="10"
                                             fill="rgba(230,255,250,0.45)" filter="blur(6px)"/>
                                    <ellipse cx="40" cy="12" rx="20" ry="5" fill="url(#torchLens)">
                                        <animate attributeName="opacity" values="1;0.85;1" dur="1.6s" repeatCount="indefinite"/>
                                    </ellipse>
                                    <ellipse cx="40" cy="12" rx="20" ry="5"
                                             fill="none" stroke="rgba(255,255,255,0.85)" stroke-width="0.9"/>
                                </svg>
                            </div>
                        </div>

                        <!-- Broom (sweeping chore) -->
                        <div class="robot-broom" id="robotBroom" aria-hidden="true">
                            <svg viewBox="0 0 60 130" xmlns="http://www.w3.org/2000/svg">
                                <rect x="26" y="6" width="8" height="80" rx="4" fill="#8b5a2b" stroke="#5b3a1a" stroke-width="1.2"/>
                                <rect x="14" y="78" width="32" height="14" rx="3" fill="#c9a26b" stroke="#8b5a2b" stroke-width="1.2"/>
                                <path d="M 14 92 Q 30 128 46 92 Z" fill="#e0c48b" stroke="#8b5a2b" stroke-width="1.2"/>
                                <line x1="20" y1="96" x2="20" y2="110" stroke="#8b5a2b" stroke-width="1"/>
                                <line x1="26" y1="98" x2="26" y2="116" stroke="#8b5a2b" stroke-width="1"/>
                                <line x1="32" y1="98" x2="32" y2="116" stroke="#8b5a2b" stroke-width="1"/>
                                <line x1="40" y1="96" x2="40" y2="110" stroke="#8b5a2b" stroke-width="1"/>
                            </svg>
                        </div>

                        <!-- Duster (dusting chore) -->
                        <div class="robot-duster" id="robotDuster" aria-hidden="true">
                            <svg viewBox="0 0 60 120" xmlns="http://www.w3.org/2000/svg">
                                <rect x="26" y="4" width="8" height="70" rx="4" fill="#5b3a1a" stroke="#3a2510" stroke-width="1.2"/>
                                <ellipse cx="30" cy="86" rx="22" ry="18" fill="#fbcfe8" stroke="#ec4899" stroke-width="1.4"/>
                                <ellipse cx="30" cy="86" rx="22" ry="18" fill="url(#dusterGrad)" opacity="0.5"/>
                                <defs>
                                    <radialGradient id="dusterGrad" cx="40%" cy="35%" r="70%">
                                        <stop offset="0%" stop-color="#ffffff"/>
                                        <stop offset="100%" stop-color="rgba(255,255,255,0)"/>
                                    </radialGradient>
                                </defs>
                                <line x1="12" y1="94" x2="48" y2="94" stroke="#ec4899" stroke-width="1"/>
                                <line x1="14" y1="100" x2="46" y2="100" stroke="#ec4899" stroke-width="1"/>
                            </svg>
                        </div>

                    </div>
                </div>

                <!-- Dust puffs while doing chores -->
                <div class="chore-dust-layer" id="choreDustLayer" aria-hidden="true"></div>
            </div>
        </div>

        <div class="form-scene" id="formScene">
            <div class="login-box" id="loginBox">

                <form class="login-form" id="loginForm"
                      action="core/auth" autocomplete="OFF" method="POST">

                    <div class="stage d-1">
                        <img class="brand-logo" src="images/logo/<?php echo WBLogo; ?>" alt="Logo">
                    </div>

                    <div class="welcome-slot stage d-2">
                        <div class="welcome-pill" id="welcomePill">
                            Welcome <span class="accent">Back.</span>
                        </div>
                    </div>

                    <p class="text-center stage d-2" style="margin-bottom:1.2rem;color:#93b8c4;font-size:0.74rem;letter-spacing:0.14em;text-transform:uppercase;text-align:center;font-weight:500;">
                        <?php echo WBName; ?> &middot; SRMS
                    </p>

                    <div class="mb-3 stage d-3">
                        <label class="form-label" for="login-username">Username</label>
                        <input id="login-username" class="form-control" type="text"
                               placeholder="Email or Registration Number"
                               required autocomplete="off" name="username">
                    </div>

                    <div class="mb-3 stage d-4">
                        <label class="form-label" for="login-password">Password</label>
                        <div class="field-wrap">
                            <input id="login-password" class="form-control" type="password"
                                   placeholder="Login Password"
                                   required autocomplete="off" name="password">
                            <button type="button" class="toggle-pw" id="toggleLoginPw" aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3 stage d-5">
                        <div class="utility">
                            <p class="semibold-text mb-0" style="margin:0;">
                                <a href="javascript:void(0);" data-toggle="flip">Forgot Password?</a>
                            </p>
                        </div>
                    </div>

                    <div class="btn-container d-grid stage d-6">
                        <button type="submit" class="btn btn-primary btn-block app_btn" id="loginSubmitBtn">
                            <i class="bi bi-box-arrow-in-right me-2 fs-5"></i>SIGN IN
                        </button>
                    </div>

                    <div class="inline-error" id="inlineError" role="alert"></div>
                </form>

                <form class="forget-form app_frm" action="core/forgot_pw" method="POST" autocomplete="OFF">
                    <div class="stage d-1">
                        <img class="brand-logo" src="images/logo/<?php echo WBLogo; ?>" alt="Logo">
                    </div>
                    <h4 style="text-align:center;color:#eaf9ff;font-family:'Poppins',sans-serif;font-weight:700;font-size:1.2rem;margin:0.35rem 0;">
                        Reset <span style="color:#5eead4;">Password.</span>
                    </h4>
                    <p class="text-center stage d-2" style="color:#93b8c4;font-size:0.72rem;letter-spacing:0.14em;text-transform:uppercase;text-align:center;margin-bottom:1.2rem;">
                        <?php echo WBName; ?> &middot; SRMS
                    </p>

                    <div class="mb-3 stage d-3">
                        <label class="form-label" for="forgot-username">Username</label>
                        <input id="forgot-username" class="form-control" type="text"
                               placeholder="Email or Registration Number"
                               required name="username">
                    </div>

                    <div class="btn-container d-grid stage d-4" style="margin-top:0.55rem;">
                        <button type="submit" class="btn btn-primary btn-block app_btn">
                            <i class="bi bi-unlock me-2 fs-5"></i>RESET PASSWORD
                        </button>
                    </div>

                    <div class="mt-3 stage d-5">
                        <p class="semibold-text mb-0" style="text-align:center;">
                            <a href="javascript:void(0);" data-toggle="flip">
                                <i class="bi bi-chevron-left me-1"></i> Back to Login
                            </a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <a href="javascript:void(0);" class="notif-bell" id="notifBell" title="Notifications">
        <i class="bi bi-bell-fill"></i>
        <span class="badge" id="notifBadge">0</span>
    </a>
    <div class="notif-toast" id="notifToast">
        <button class="btn-close-toast" id="closeToast">&times;</button>
        <div class="toast-title"><i class="bi bi-megaphone-fill"></i> New Announcement</div>
        <div class="toast-body" id="toastMessage"></div>
        <div class="toast-actions"><a href="javascript:void(0);" id="toastViewLink">View Details</a></div>
    </div>

    <div class="fw-layer" id="fwLayer" aria-hidden="true"></div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/forms.js"></script>
<script src="js/sweetalert2@11.js"></script>

<script>
(function(){
'use strict';

const $ = (s, r) => (r||document).querySelector(s);
const sleep = ms => new Promise(r => setTimeout(r, ms));

const robotStage    = $('#robotStage');
const robotWrapper  = $('#robotWrapper');
const robot         = $('#robot');
const pupils        = [$('.pupil-left', robot), $('.pupil-right', robot)];
const eyes          = [$('.eye-left',   robot), $('.eye-right',   robot)];
const thoughtEl     = $('#thoughtBubble');
const welcomePill   = $('#welcomePill');
const loginForm     = $('#loginForm');
const loginBox      = $('#loginBox');
const formScene     = $('#formScene');
const cursorLight   = $('#cursorLight');
const cursorReveal  = $('#cursorReveal');
const torchAimEl    = $('#torchAim');
const dustLayer     = $('#choreDustLayer');
const usernameInput = $('#login-username');
const passwordInput = $('#login-password');
const submitBtn     = $('#loginSubmitBtn');
const togglePwBtn   = $('#toggleLoginPw');
const inlineError   = $('#inlineError');
const fwLayer       = $('#fwLayer');
const B             = document.body.classList;

/* ---------- bubbles ---------- */
(function bubbles(){
    const layer = $('#bubblesLayer');
    if (!layer) return;
    const n = window.innerWidth < 700 ? 12 : 22;
    for (let i=0;i<n;i++){
        const b = document.createElement('div');
        b.className = 'bubble';
        const s = 6 + Math.random()*26;
        b.style.width = b.style.height = s + 'px';
        b.style.left = Math.random()*100 + '%';
        b.style.top = (100 + Math.random()*30) + '%';
        b.style.animationDuration = (14 + Math.random()*22) + 's';
        b.style.animationDelay = (-Math.random()*20) + 's';
        layer.appendChild(b);
    }
})();

/* ---------- eyes ---------- */
const MAX_X = 6, MAX_Y = 5, SMOOTH = 0.16;
let targetX=0, targetY=0, currentX=0, currentY=0;
let attention = 'cursor';

function robotCenter(){
    if (!robot) return {x: innerWidth/2, y: innerHeight/2};
    const r = robot.getBoundingClientRect();
    return {x: r.left + r.width/2, y: r.top + r.height/2};
}
function targetFromPoint(px, py){
    const c = robotCenter();
    const dx = px - c.x, dy = py - c.y;
    const dist = Math.hypot(dx, dy) || 1;
    const factor = Math.min(dist/280, 1);
    const a = Math.atan2(dy, dx);
    targetX = Math.cos(a) * MAX_X * factor;
    targetY = Math.sin(a) * MAX_Y * factor;
}
function targetFromElement(el, intensity){
    if (!el) return;
    const r = el.getBoundingClientRect();
    targetFromPoint(r.left + r.width/2, r.top + r.height/2);
    const k = intensity || 1;
    targetX *= k; targetY *= k;
}

let mouseX = innerWidth/2, mouseY = innerHeight/2;
let torchAngleTarget = 0, torchAngleSmooth = 0;
const TORCH_BASE = -14;
const TORCH_MAX  = 32;

function tick(){
    currentX += (targetX - currentX) * SMOOTH;
    currentY += (targetY - currentY) * SMOOTH;
    const t = `translate(${currentX.toFixed(2)}px, ${currentY.toFixed(2)}px)`;
    pupils.forEach(p => p && (p.style.transform = t));

    if (torchAimEl && torchActive){
        const rr = robot.getBoundingClientRect();
        const pivotX = rr.right + rr.width * 0.06;
        const pivotY = rr.bottom - rr.height * 0.06;
        const dx = mouseX - pivotX;
        const dy = mouseY - pivotY;
        const dist = Math.hypot(dx, dy);
        if (dist > 6){
            let angle = Math.atan2(dx, -dy) * 180 / Math.PI;
            if (angle > 180) angle -= 360;
            if (angle < -180) angle += 360;
            angle = Math.max(-TORCH_MAX, Math.min(TORCH_MAX, angle));
            torchAngleTarget = angle;
        } else torchAngleTarget = 0;
        torchAngleSmooth += (torchAngleTarget - torchAngleSmooth) * 0.14;
        torchAimEl.style.transform =
            `rotate(${(TORCH_BASE + torchAngleSmooth).toFixed(2)}deg)`;
    }

    requestAnimationFrame(tick);
}
requestAnimationFrame(tick);

/* ---------- blink ---------- */
(function blink(){
    if (!eyes[0]) return;
    function schedule(){
        setTimeout(() => {
            const busy =
                B.contains('robot-sad') ||
                B.contains('robot-laughing') ||
                B.contains('robot-yawning') ||
                B.contains('robot-winking') ||
                B.contains('robot-chore') ||
                B.contains('submitting') ||
                B.contains('robot-touched') ||
                B.contains('robot-glasses') ||
                B.contains('robot-reset');
            if (busy) return schedule();
            eyes.forEach(e => e.style.transform = 'scaleY(0.08)');
            setTimeout(() => eyes.forEach(e => e.style.transform = 'scaleY(1)'), 110);
            schedule();
        }, 3000 + Math.random()*4000);
    }
    schedule();
})();

/* ---------- thoughts ---------- */
let thoughtTimer = null;
function showThought(html, durationMs, variantClass){
    if (thoughtTimer) { clearTimeout(thoughtTimer); thoughtTimer = null; }
    if (!thoughtEl) return;
    thoughtEl.classList.remove('thought-red');
    if (variantClass) thoughtEl.classList.add(variantClass);
    thoughtEl.innerHTML = html;
    B.remove('thought-show', 'thought-hide');
    void thoughtEl.offsetWidth;
    B.add('thought-show');
    if (durationMs > 0){
        thoughtTimer = setTimeout(() => {
            B.remove('thought-show');
            B.add('thought-hide');
            thoughtTimer = setTimeout(() => {
                B.remove('thought-hide');
                thoughtEl.classList.remove('thought-red');
                thoughtTimer = null;
            }, 700);
        }, durationMs);
    }
}
function clearThought(){
    if (thoughtTimer) { clearTimeout(thoughtTimer); thoughtTimer = null; }
    B.remove('thought-show','thought-hide');
    if (thoughtEl) thoughtEl.classList.remove('thought-red');
}

/* =========================================================
   ROBOT CHORES
   A small routine that walks the robot around the stage,
   then plays a "chore" (sweep or dust) with dust puffs.
   ========================================================= */

// How far left/right the robot can walk inside the stage (in % of stage width)
const WALK_RANGE_PCT = 26;

let choreActive = false;
let choreTimeout = null;
let wanderTimer = null;

function walkTo(pct, durMs){
    B.add('robot-walking');
    robotWrapper.style.setProperty('--walk-dur', durMs + 'ms');
    // pct: -1 (far left) .. +1 (far right)
    const px = pct * WALK_RANGE_PCT;
    robotWrapper.style.transform = `translateX(calc(-50% + ${px}%)) translateY(0)`;
    setTimeout(() => B.remove('robot-walking'), durMs);
}

function spawnDustPuff(){
    if (!dustLayer) return;
    const p = document.createElement('div');
    p.className = 'dust-puff';
    const dx = (Math.random() - 0.5) * 60;
    p.style.left = (30 + Math.random() * 40) + '%';
    p.style.setProperty('--dx', dx.toFixed(0) + 'px');
    p.style.animationDuration = (1.2 + Math.random() * 0.8) + 's';
    dustLayer.appendChild(p);
    setTimeout(() => p.remove(), 2200);
}

async function performSweep(){
    choreActive = true;
    B.add('robot-chore','chore-sweep');

    // Sweep for ~3 s while emitting dust
    const dustTimer = setInterval(spawnDustPuff, 220);
    await sleep(3000);
    clearInterval(dustTimer);

    B.remove('robot-chore','chore-sweep');
    choreActive = false;
}

async function performDust(){
    choreActive = true;
    B.add('robot-chore','chore-dust');

    const dustTimer = setInterval(spawnDustPuff, 260);
    await sleep(2600);
    clearInterval(dustTimer);

    B.remove('robot-chore','chore-dust');
    choreActive = false;
}

async function choreRoutine(){
    if (choreActive) return;
    if (B.contains('robot-reset') || B.contains('submitting') || isFormFocused()) return;

    // Pick a chore type
    const kind = Math.random() < 0.5 ? 'sweep' : 'dust';

    // Pick a random spot on the stage
    const pct = (Math.random() * 2 - 1) * 0.7; // -0.7 .. +0.7
    const walkTime = 2400 + Math.random() * 1200;
    walkTo(pct, walkTime);

    // Arrive → do the chore
    await sleep(walkTime + 200);
    if (kind === 'sweep') await performSweep();
    else                  await performDust();

    // Walk back toward center
    walkTo(0, 2200);
    await sleep(2200);

    // Chance to speak a thought after chores
    if (Math.random() < 0.7){
        const lines = [
            'Isn\u2019t it about time we <span class="accent">login</span>?',
            'Hmm\u2026 are we <span class="accent">logging in</span> today?',
            'Still waiting for your <span class="accent">credentials</span>\u2026',
            'I tidied up. Ready when you are <span class="accent">:)</span>'
        ];
        showThought(lines[Math.floor(Math.random()*lines.length)], 3200);
    }
}

function scheduleChores(){
    function loop(){
        wanderTimer = setTimeout(async () => {
            await choreRoutine();
            loop();
        }, 9000 + Math.random() * 6000); // every 9–15 s
    }
    loop();
}

/* ---------- real fireworks ---------- */
const PALETTES = [
    { c1:'#ffe08a', c2:'#f59e0b' },
    { c1:'#a5f3fc', c2:'#22d3ee' },
    { c1:'#fbcfe8', c2:'#ec4899' },
    { c1:'#d9f99d', c2:'#84cc16' },
    { c1:'#f0abfc', c2:'#a855f7' },
    { c1:'#ffffff', c2:'#f87171' },
];
function rand(min, max){ return min + Math.random() * (max - min); }

function launchFirework(sx, sy, tx, ty, paletteIdx){
    if (!fwLayer) return;
    const pal = PALETTES[paletteIdx % PALETTES.length];
    const rx = tx - sx, ry = ty - sy;
    const dur = rand(0.75, 1.05);

    const rocket = document.createElement('div');
    rocket.className = 'fw-rocket';
    rocket.style.left = sx + 'px';
    rocket.style.top  = sy + 'px';
    rocket.style.setProperty('--rx', rx + 'px');
    rocket.style.setProperty('--ry', ry + 'px');
    rocket.style.setProperty('--dur', dur + 's');
    fwLayer.appendChild(rocket);

    const trailCount = 7;
    for (let i = 0; i < trailCount; i++){
        const t = (i + 1) / (trailCount + 1);
        const trail = document.createElement('div');
        trail.className = 'fw-trail';
        trail.style.left = (sx + rx * t) + 'px';
        trail.style.top  = (sy + ry * t) + 'px';
        trail.style.setProperty('--dur', '0.55s');
        trail.style.setProperty('--delay', ((dur * t) + 0.1) + 's');
        fwLayer.appendChild(trail);
        setTimeout(() => trail.remove(), (dur * 1000) + 600);
    }
    setTimeout(() => rocket.remove(), (dur * 1000) + 100);
    setTimeout(() => spawnBurst(tx, ty, pal), dur * 1000);
}

function spawnBurst(x, y, pal){
    const burst = document.createElement('div');
    burst.className = 'fw-burst';
    burst.style.left = x + 'px';
    burst.style.top  = y + 'px';

    const flash = document.createElement('div');
    flash.className = 'fw-flash';
    burst.appendChild(flash);

    const sparkCount = 26;
    for (let i = 0; i < sparkCount; i++){
        const angle = (i / sparkCount) * Math.PI * 2 + rand(-0.05, 0.05);
        const dist  = rand(55, 130);
        const fx = Math.cos(angle) * dist;
        const fy = Math.sin(angle) * dist - rand(0, 12);

        const s = document.createElement('div');
        s.className = 'fw-spark';
        s.style.setProperty('--fx', fx.toFixed(1) + 'px');
        s.style.setProperty('--fy', fy.toFixed(1) + 'px');
        s.style.setProperty('--c1', pal.c1);
        s.style.setProperty('--c2', pal.c2);
        s.style.setProperty('--dur', rand(1.15, 1.55) + 's');
        s.style.setProperty('--delay', rand(0, 0.08) + 's');
        burst.appendChild(s);
    }

    for (let i = 0; i < 12; i++){
        const angle = Math.random() * Math.PI * 2;
        const dist  = rand(20, 90);
        const fx = Math.cos(angle) * dist;
        const fy = Math.sin(angle) * dist;

        const c = document.createElement('div');
        c.className = 'fw-crackle';
        c.style.left = fx + 'px';
        c.style.top  = fy + 'px';
        c.style.setProperty('--delay', rand(0, 0.4) + 's');
        burst.appendChild(c);
    }
    fwLayer.appendChild(burst);
    setTimeout(() => burst.remove(), 2200);
}

function throwFireworks(){
    if (!fwLayer || !robot) return;
    const rr = robot.getBoundingClientRect();
    const sx = rr.right - rr.width * 0.10;
    const sy = rr.top  + rr.height * 0.55;

    const targets = [
        { dx:  rand(120, 220), dy: -rand(160, 240) },
        { dx:  rand(60,  140), dy: -rand(200, 260) },
        { dx: -rand(60,  120), dy: -rand(180, 240) },
        { dx:  rand(20,   80), dy: -rand(140, 200) },
        { dx:  rand(180, 280), dy: -rand(120, 180) },
    ];
    targets.forEach((t, i) => {
        const tx = sx + t.dx;
        const ty = sy + t.dy;
        setTimeout(() => launchFirework(sx, sy, tx, ty, i), i * 130);
    });
}

/* ---------- welcome sequence ---------- */
function playWelcomeSequence(){
    setTimeout(() => { showThought('Welcome <span class="accent">Back.</span>', 1400); }, 2000);
    setTimeout(() => { B.add('welcome-in'); }, 3750);
    setTimeout(() => { showThought('Hello user, please fill your credentials', 3000); }, 5000);
    // Start chores after the intro
    setTimeout(scheduleChores, 7000);
}

/* ---------- torch mode ---------- */
const TORCH_EXPRS = ['torch-thinking', 'torch-shocked', 'torch-wondering'];
let torchActive = false;
let exprTimer = null;
let exprIndex = -1;

function pickExpression(){
    let next;
    do { next = Math.floor(Math.random() * TORCH_EXPRS.length); }
    while (next === exprIndex && TORCH_EXPRS.length > 1);
    exprIndex = next;
    TORCH_EXPRS.forEach(c => B.remove(c));
    B.add(TORCH_EXPRS[next]);
}
function enterTorchMode(){
    if (torchActive) return;
    torchActive = true;
    B.add('torch-on');
    pickExpression();
    if (exprTimer) clearInterval(exprTimer);
    exprTimer = setInterval(pickExpression, 2600);
}
function exitTorchMode(){
    if (!torchActive) return;
    torchActive = false;
    B.remove('torch-on');
    TORCH_EXPRS.forEach(c => B.remove(c));
    if (exprTimer) { clearInterval(exprTimer); exprTimer = null; }
    flashHappy(1100);
}

/* ---------- pointer handling ---------- */
const canHover = window.matchMedia('(hover: hover)').matches;

let robotTouchCount = 0;
let wasTouchingRobot = false;

function isFormFocused(){
    const a = document.activeElement;
    return a && (a.id === 'login-username' || a.id === 'login-password' || a.id === 'forgot-username');
}
function insideRect(x, y, r, pad){
    pad = pad || 0;
    return x >= r.left - pad && x <= r.right + pad &&
           y >= r.top  - pad && y <= r.bottom + pad;
}

function handlePointer(e){
    if (!formScene) return;
    const x = e.clientX, y = e.clientY;
    mouseX = x; mouseY = y;

    if (cursorLight)  cursorLight.style.transform  = `translate3d(${x}px, ${y}px, 0)`;
    if (cursorReveal) cursorReveal.style.transform = `translate3d(${x}px, ${y}px, 0)`;

    if (robot){
        const rr = robot.getBoundingClientRect();
        const touching = insideRect(x, y, rr, 0);
        if (touching && !wasTouchingRobot){
            robotTouchCount++;
            if (robotTouchCount === 1) B.add('robot-touched');
            else { B.remove('robot-touched'); B.add('robot-glasses'); }
        } else if (!touching && wasTouchingRobot){
            B.remove('robot-touched');
            B.remove('robot-glasses');
        }
        wasTouchingRobot = touching;
    }

    const fr = formScene.getBoundingClientRect();
    const lr = loginBox ? loginBox.getBoundingClientRect() : null;
    const overForm = insideRect(x, y, fr, 8) || (lr && insideRect(x, y, lr, 8));
    const focused = isFormFocused();

    if (overForm || focused){
        exitTorchMode();
    } else {
        enterTorchMode();
        attention = 'cursor';
        targetFromPoint(x, y);
    }
}

if (canHover){
    window.addEventListener('pointermove', handlePointer, {passive:true});
    window.addEventListener('pointerleave', () => {
        exitTorchMode();
        B.remove('robot-touched');
        B.remove('robot-glasses');
        wasTouchingRobot = false;
    });
}

/* ---------- face helpers ---------- */
function flashHappy(ms){
    if (B.contains('robot-sad')) B.remove('robot-sad');
    B.add('robot-happy');
    setTimeout(() => B.remove('robot-happy'), ms || 1200);
}
function flashFace(cls, ms){
    if (B.contains('robot-sad')) B.remove('robot-sad');
    B.add(cls);
    setTimeout(() => B.remove(cls), ms || 1500);
}
function resetErrorState(){
    if (B.contains('login-error')){
        B.remove('login-error');
        swapPill('Welcome <span class="accent">Back.</span>', null);
    }
    if (B.contains('robot-sad')) B.remove('robot-sad');
    clearThought();
    inlineError.classList.remove('show');
}

/* ---------- idle faces (short, non-chore) ---------- */
(function idleFaces(){
    function schedule(){
        const delay = 15000 + Math.random() * 12000;
        setTimeout(() => {
            const busy =
                B.contains('robot-sad') ||
                B.contains('robot-laughing') ||
                B.contains('robot-reset') ||
                B.contains('robot-glasses') ||
                B.contains('robot-chore') ||
                B.contains('submitting') ||
                isFormFocused();
            if (busy) return schedule();

            const pick = Math.random();
            if (pick < 0.35)      flashFace('robot-yawning', 2400);
            else if (pick < 0.65) flashFace('robot-winking', 900);
            else                  flashFace('robot-love', 1800);

            schedule();
        }, delay);
    }
    schedule();
})();

/* ---------- focus / hover ---------- */
usernameInput.addEventListener('focus', () => {
    attention = 'username';
    targetFromElement(usernameInput, 1.0);
    flashHappy(1200);
    B.remove('robot-focus');
    resetErrorState();
    exitTorchMode();
});
usernameInput.addEventListener('blur', () => {
    if (attention === 'username'){ attention = 'cursor'; targetX = targetY = 0; }
    B.remove('robot-focus');
});
usernameInput.addEventListener('input', () => {
    resetErrorState();
    B.add('robot-focus');
    clearTimeout(window._focusTimer);
    window._focusTimer = setTimeout(() => B.remove('robot-focus'), 1400);
});

passwordInput.addEventListener('focus', () => {
    attention = 'password';
    targetFromElement(passwordInput, 1.0);
    robot && robot.classList.add('eye-dim');
    flashHappy(1500);
    B.remove('robot-focus');
    resetErrorState();
    exitTorchMode();
});
passwordInput.addEventListener('blur', () => {
    if (attention === 'password'){
        attention = 'cursor';
        targetX = targetY = 0;
        robot && robot.classList.remove('eye-dim');
    }
    B.remove('robot-focus');
});
passwordInput.addEventListener('input', () => {
    resetErrorState();
    B.add('robot-focus');
    clearTimeout(window._focusTimer);
    window._focusTimer = setTimeout(() => B.remove('robot-focus'), 1400);
});

togglePwBtn && togglePwBtn.addEventListener('click', () => {
    const visible = passwordInput.type === 'text';
    passwordInput.type = visible ? 'password' : 'text';
    togglePwBtn.querySelector('i').className = visible ? 'bi bi-eye' : 'bi bi-eye-slash';
    togglePwBtn.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
    flashFace('robot-surprised', 500);
    setTimeout(() => flashFace('robot-winking', 800), 500);
});

function enterReady(){
    if (B.contains('submitting')) return;
    attention = 'button';
    targetFromElement(submitBtn, 1.0);
}
function leaveReady(){
    if (B.contains('submitting')) return;
    if (attention === 'button'){ attention = 'cursor'; targetX = targetY = 0; }
}
submitBtn.addEventListener('mouseenter', enterReady);
submitBtn.addEventListener('mouseleave', leaveReady);
submitBtn.addEventListener('focus',      enterReady);
submitBtn.addEventListener('blur',       leaveReady);

/* ---------- flip toggle ---------- */
document.addEventListener('click', e => {
    const t = e.target.closest('[data-toggle="flip"]');
    if (!t) return;
    e.preventDefault();
    loginBox.classList.toggle('flipped');
    attention = 'cursor';
    targetX = targetY = 0;
    inlineError.classList.remove('show');

    if (loginBox.classList.contains('flipped')){
        B.remove('robot-chore','chore-sweep','chore-dust','robot-walking');
        B.add('robot-reset');
        showThought('Oops! What did we <span class="accent-red">forget?</span>', 3200, 'thought-red');

        if (window._resetLoop) clearInterval(window._resetLoop);
        window._resetLoop = setInterval(() => {
            if (!loginBox.classList.contains('flipped')){
                clearInterval(window._resetLoop);
                window._resetLoop = null;
                return;
            }
            showThought('Oops! What did we <span class="accent-red">forget?</span>', 3200, 'thought-red');
        }, 6000);
    } else {
        if (window._resetLoop) { clearInterval(window._resetLoop); window._resetLoop = null; }
        B.remove('robot-reset');
        clearThought();
    }
});

/* ---------- pill swap ---------- */
function swapPill(html, variantClass){
    if (!welcomePill) return;
    welcomePill.classList.remove('swap','pill-success','pill-error');
    welcomePill.innerHTML = html;
    if (variantClass) welcomePill.classList.add(variantClass);
    void welcomePill.offsetWidth;
    welcomePill.classList.add('swap');
}

/* ---------- envelope ---------- */
const ENVELOPE_HTML = `
    <svg class="envelope-svg" viewBox="0 0 140 100" preserveAspectRatio="xMidYMid meet"
         xmlns="http://www.w3.org/2000/svg">
        <defs>
            <linearGradient id="envBodyGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                <stop offset="0%"  stop-color="#f8fafc"/>
                <stop offset="100%" stop-color="#cbd5e1"/>
            </linearGradient>
            <linearGradient id="envFlapGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                <stop offset="0%"  stop-color="#e2e8f0"/>
                <stop offset="100%" stop-color="#94a3b8"/>
            </linearGradient>
            <linearGradient id="envFrontGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                <stop offset="0%"  stop-color="#ffffff"/>
                <stop offset="100%" stop-color="#cbd5e1"/>
            </linearGradient>
        </defs>
        <rect x="10" y="25" width="120" height="70" rx="8" fill="url(#envBodyGrad)"
              stroke="#475569" stroke-width="1.6"/>
        <g class="env-credentials">
            <circle cx="52" cy="58" r="6"  fill="#0ea5b7" stroke="#0b7285" stroke-width="1"/>
            <circle cx="70" cy="63" r="5"  fill="#22d3ee" stroke="#0e7490" stroke-width="1"/>
            <circle cx="88" cy="56" r="7"  fill="#14b8a6" stroke="#0f766e" stroke-width="1"/>
        </g>
        <rect x="10" y="52" width="120" height="43" rx="8" fill="url(#envFrontGrad)"
              stroke="#475569" stroke-width="1.6"/>
        <path class="env-flap"
              d="M 10 25 L 70 72 L 130 25 Z"
              fill="url(#envFlapGrad)" stroke="#475569" stroke-width="1.6" stroke-linejoin="round"/>
    </svg>
    <span class="env-label">SIGNING IN…</span>
`;

async function playEnvelopeSequence(){
    return new Promise(resolve => {
        submitBtn.innerHTML = ENVELOPE_HTML;
        submitBtn.classList.add('btn-envelope');
        void submitBtn.offsetWidth;
        setTimeout(() => submitBtn.classList.add('env-open'), 100);
        setTimeout(() => submitBtn.classList.add('env-fill'), 500);
        setTimeout(() => submitBtn.classList.remove('env-open'), 900);
        setTimeout(() => submitBtn.classList.add('env-depart'), 1200);
        setTimeout(() => {
            submitBtn.classList.remove('btn-envelope','env-open','env-fill','env-depart');
            resolve();
        }, 1750);
    });
}

/* ---------- AJAX login ---------- */
let isSubmitting = false;

async function performLogin(){
    if (isSubmitting) return;
    isSubmitting = true;

    B.remove('login-success','login-error','robot-happy','robot-sad','robot-focus','robot-chore','chore-sweep','chore-dust','robot-walking');
    B.add('submitting');
    exitTorchMode();
    clearThought();

    submitBtn.setAttribute('disabled','disabled');
    const originalHTML = submitBtn.innerHTML;

    const fetchPromise = (async () => {
        try {
            const fd = new FormData(loginForm);
            const res = await fetch(loginForm.action, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                redirect: 'follow'
            });
            let bodyText = '';
            try { bodyText = await res.text(); } catch(e){}

            const looksLikeLoginPage = (function isLoginPageHtml(html){
                if (!html || typeof html !== 'string') return true;
                const markers = [
                    /id=["']loginForm["']/i,
                    /action=["']core\/auth["']/i,
                    /data-toggle=["']flip["']/i,
                    /id=["']welcomePill["']/i,
                    /id=["']loginSubmitBtn["']/i
                ];
                let hits = 0;
                for (let i = 0; i < markers.length; i++){
                    if (markers[i].test(html)) hits++;
                }
                return hits >= 2;
            })(bodyText);

            return {
                success: bodyText.length > 0 && !looksLikeLoginPage,
                url: res.url,
                bodyText
            };
        } catch (err) {
            console.error('[login]', err);
            return { success: false, url: '', bodyText: '' };
        }
    })();

    await playEnvelopeSequence();
    submitBtn.innerHTML =
        '<i class="bi bi-hourglass-split me-2 fs-5"></i>VERIFYING…';

    const result = await fetchPromise;
    B.remove('submitting');

    if (result.success){
        await handleSuccess((result.url && result.url !== '') ? result.url : '../');
    } else {
        handleFailure(result.bodyText);
    }

    submitBtn.classList.remove('btn-envelope','env-open','env-fill','env-depart');
    submitBtn.innerHTML = originalHTML;
    submitBtn.removeAttribute('disabled');
    isSubmitting = false;
}

async function handleSuccess(target){
    B.remove('submitting');
    B.add('robot-happy','robot-laughing');
    eyes.forEach(e => e.style.transform = 'scaleY(1.25)');
    B.add('login-success');
    swapPill('GREAT, <span class="accent">LETS GO!</span>', 'pill-success');

    throwFireworks();
    setTimeout(throwFireworks, 700);

    await sleep(1700);
    window.location.href = target;
}

function handleFailure(responseHtml){
    B.remove('submitting');
    const msg = extractError(responseHtml);
    B.add('robot-sad');
    B.add('login-error');
    swapPill('PLEASE <span class="accent">TRY AGAIN</span>', 'pill-error');
    inlineError.textContent = msg;
    inlineError.classList.add('show');
    showThought(
        'Hmm… those credentials don\u2019t look <span class="accent-red">right.</span>',
        6000,
        'thought-red'
    );
    setTimeout(() => flashFace('robot-angry', 1200), 2000);
}

function extractError(html){
    if (!html) return 'Invalid login credentials.';
    try {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const scripts = doc.querySelectorAll('script');
        for (const s of scripts){
            const txt = s.textContent || '';
            let m = txt.match(/Swal\.fire\s*\(\s*(?:["'`]([^"'`]+)["'`])/i);
            if (m && m[1]) return m[1];
            m = txt.match(/text\s*:\s*["'`]([^"'`]+)["'`]/i);
            if (m && m[1]) return m[1];
            m = txt.match(/title\s*:\s*["'`]([^"'`]+)["'`]/i);
            if (m && m[1]) return m[1];
            m = txt.match(/danger["'\s,:]+(["'`])([^"'`]+)\1/i);
            if (m && m[2]) return m[2];
        }
    } catch(e){}
    return 'Invalid login credentials.';
}

loginForm.addEventListener('submit', e => {
    e.preventDefault();
    performLogin();
});

/* ---------- boot ---------- */
window.addEventListener('load', () => {
    playWelcomeSequence();
});

})();
</script>

<script>
(function() {
    'use strict';
    const VAPID_PUBLIC_KEY = 'YOUR_VAPID_PUBLIC_KEY';
    async function registerServiceWorker() {
        if ('serviceWorker' in navigator && 'PushManager' in window) {
            try {
                const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
                const existingSubscription = await registration.pushManager.getSubscription();
                if (existingSubscription) { sendSubscriptionToServer(existingSubscription); return; }
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') return;
                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
                });
                sendSubscriptionToServer(subscription);
            } catch (err) { console.error('[SW]', err); }
        }
    }
    function sendSubscriptionToServer(subscription) {
        const data = {
            endpoint: subscription.endpoint,
            keys: {
                auth: btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('auth')))),
                p256dh: btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('p256dh'))))
            }
        };
        $.ajax({
            url: 'core/subscribe.php', method: 'POST',
            data: { subscription: JSON.stringify(data) }, dataType: 'json',
            success: res => { if (res.success) console.log('[SW] Subscription saved.'); },
            error: () => console.error('[SW] Error sending subscription.')
        });
    }
    function urlBase64ToUint8Array(b64){
        const pad='='.repeat((4-b64.length%4)%4);
        const b=(b64+pad).replace(/-/g,'+').replace(/_/g,'/');
        const raw=window.atob(b);const out=new Uint8Array(raw.length);
        for(let i=0;i<raw.length;++i) out[i]=raw.charCodeAt(i);
        return out;
    }
    $(document).ready(registerServiceWorker);

    function getCookie(name){
        const m=document.cookie.match(new RegExp('(^| )'+name+'=([^;]+)'));
        return m?m[2]:null;
    }
    function setCookie(name,value,days){
        const d=new Date(); d.setTime(d.getTime()+(days*24*60*60*1000));
        document.cookie=name+"="+value+"; expires="+d.toUTCString()+"; path=/";
    }
    let lastNotifId=getCookie('last_notif_id')||0;

    function checkNotifications(){
        $.ajax({
            url: 'core/get_notifications.php', method: 'GET', dataType: 'json',
            success: function(response){
                if (!response.success || !response.notifications || !response.notifications.length){
                    updateBadge(0); return;
                }
                const notifs = response.notifications;
                let unseen = 0, latestId = lastNotifId;
                notifs.forEach(n => { if (n.id > lastNotifId){ unseen++; if (n.id>latestId) latestId=n.id; } });
                if (unseen>0){
                    showToast(notifs[0].message);
                    updateBadge(unseen);
                    setCookie('last_notif_id', latestId, 7);
                    lastNotifId = latestId;
                } else updateBadge(0);
            },
            error: function(){ /* silent */ }
        });
    }
    function updateBadge(c){ $('#notifBadge').text(c).toggle(c>0); }
    let toastTimer;
    function showToast(msg){
        const $t=$('#notifToast'); $('#toastMessage').text(msg);
        $t.addClass('show'); clearTimeout(toastTimer);
        toastTimer=setTimeout(()=>$t.removeClass('show'),8000);
    }
    $('#closeToast').click(()=>{ $('#notifToast').removeClass('show'); clearTimeout(toastTimer); });
    $('#notifBell').click(function(){
        const $t=$('#notifToast');
        if ($t.hasClass('show')) $t.removeClass('show');
        else $.ajax({url:'core/get_notifications.php',method:'GET',dataType:'json',
            success:r=>{ if(r.success&&r.notifications&&r.notifications.length) showToast(r.notifications[0].message); }
        });
    });
    checkNotifications();
    setInterval(checkNotifications, 30000);
})();
</script>

<script type="module">
// Keep your existing WebGL shader + stars module code here.
</script>

<?php require_once('const/check-reply.php'); ?>
</body>
</html>