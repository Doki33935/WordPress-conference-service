(function () {
  'use strict';

  var theme = document.querySelector('.conference-theme-ember');
  var hero = theme ? theme.querySelector('.hero') : null;
  var atmosphere = hero ? hero.querySelector('.theme-atmosphere') : null;
  var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  if (!hero || !atmosphere || reducedMotion.matches) {
    return;
  }

  var canvas = document.createElement('canvas');
  var context = canvas.getContext('2d');

  if (!context) {
    return;
  }

  canvas.className = 'ember-particle-canvas';
  canvas.setAttribute('aria-hidden', 'true');
  atmosphere.appendChild(canvas);

  var particles = [];
  var width = 0;
  var height = 0;
  var frameId = 0;
  var active = false;
  var lastFrame = performance.now();
  var nextEmission = lastFrame;

  function random(min, max) {
    return min + Math.random() * (max - min);
  }

  function resizeCanvas() {
    var bounds = hero.getBoundingClientRect();
    var scale = Math.min(window.devicePixelRatio || 1, 1.5);

    width = Math.max(1, bounds.width);
    height = Math.max(1, bounds.height);
    canvas.width = Math.round(width * scale);
    canvas.height = Math.round(height * scale);
    canvas.style.width = width + 'px';
    canvas.style.height = height + 'px';
    context.setTransform(scale, 0, 0, scale, 0, 0);
  }

  function createParticle() {
    var life = random(0.8, 2.15);
    var x = random(width * 0.08, width * 0.96);
    var y = random(height * 0.78, height * 1.02);

    particles.push({
      x: x,
      y: y,
      previousX: x,
      previousY: y,
      velocityX: random(-72, 72),
      velocityY: random(-310, -145),
      age: 0,
      life: life,
      radius: random(0.7, 2.15),
      hue: random(18, 52),
      lightness: random(58, 88),
      phase: random(0, Math.PI * 2),
      turbulence: random(28, 92),
      frequency: random(4.5, 10.5)
    });
  }

  function emitParticles(now) {
    if (now < nextEmission || particles.length >= 26) {
      return;
    }

    var burst = Math.random() < 0.2 ? Math.floor(random(2, 4)) : 1;

    for (var index = 0; index < burst && particles.length < 26; index += 1) {
      createParticle();
    }

    nextEmission = now + random(90, 390);
  }

  function drawParticle(particle, delta) {
    var progress = particle.age / particle.life;
    var fadeIn = Math.min(1, progress * 8);
    var fadeOut = Math.pow(Math.max(0, 1 - progress), 1.7);
    var flicker = 0.78 + Math.sin(particle.age * 31 + particle.phase) * 0.16;
    var alpha = fadeIn * fadeOut * flicker;
    var wind = Math.sin(particle.age * particle.frequency + particle.phase) * particle.turbulence;

    particle.previousX = particle.x;
    particle.previousY = particle.y;
    particle.velocityX += wind * delta;
    particle.velocityX *= Math.pow(0.91, delta);
    particle.velocityY += 58 * delta;
    particle.x += particle.velocityX * delta;
    particle.y += particle.velocityY * delta;
    particle.age += delta;

    context.beginPath();
    context.moveTo(particle.previousX, particle.previousY);
    context.lineTo(
      particle.x - particle.velocityX * delta * 1.8,
      particle.y - particle.velocityY * delta * 1.8
    );
    context.lineWidth = particle.radius;
    context.lineCap = 'round';
    context.strokeStyle = 'hsla(' + particle.hue + ', 100%, ' + particle.lightness + '%, ' + alpha + ')';
    context.shadowBlur = 8 + particle.radius * 4;
    context.shadowColor = 'hsla(' + particle.hue + ', 100%, 58%, ' + alpha + ')';
    context.stroke();

    context.beginPath();
    context.arc(particle.x, particle.y, particle.radius * 0.65, 0, Math.PI * 2);
    context.fillStyle = 'hsla(' + Math.min(58, particle.hue + 8) + ', 100%, 88%, ' + alpha + ')';
    context.fill();
  }

  function render(now) {
    if (!active) {
      return;
    }

    var delta = Math.min((now - lastFrame) / 1000, 0.034);
    lastFrame = now;
    context.clearRect(0, 0, width, height);
    context.shadowBlur = 0;
    emitParticles(now);

    particles.forEach(function (particle) {
      drawParticle(particle, delta);
    });

    particles = particles.filter(function (particle) {
      return particle.age < particle.life && particle.y > -40;
    });

    frameId = window.requestAnimationFrame(render);
  }

  function setActive(isActive) {
    if (active === isActive) {
      return;
    }

    active = isActive;

    if (active) {
      lastFrame = performance.now();
      nextEmission = lastFrame;
      frameId = window.requestAnimationFrame(render);
      return;
    }

    window.cancelAnimationFrame(frameId);
    particles = [];
    context.clearRect(0, 0, width, height);
  }

  resizeCanvas();

  if ('ResizeObserver' in window) {
    new ResizeObserver(resizeCanvas).observe(hero);
  } else {
    window.addEventListener('resize', resizeCanvas);
  }

  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (entries) {
      setActive(entries[0].isIntersecting);
    }, { threshold: 0.08 }).observe(hero);
  } else {
    setActive(true);
  }
}());
