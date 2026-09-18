
(function () {
  'use strict';

  var VERT = [
    'attribute vec2 aPos;',
    'varying vec2 vUv;',
    'void main() {',
    '  vUv = aPos * 0.5 + 0.5;',
    '  gl_Position = vec4(aPos, 0.0, 1.0);',
    '}'
  ].join('\n');

  function buildFragment(settings) {
    return [
      'precision ' + settings.precision + ' float;',
      '',
      'uniform float uTime;',
      'uniform vec2  uResolution;',
      'uniform vec2  uMouse;',
      'uniform vec3  uTopColor;',
      'uniform vec3  uBottomColor;',
      'uniform float uIntensity;',
      'uniform bool  uInteractive;',
      'uniform float uGlowAmount;',
      'uniform float uPillarWidth;',
      'uniform float uPillarHeight;',
      'uniform float uNoiseIntensity;',
      'uniform float uRotCos;',
      'uniform float uRotSin;',
      'uniform float uPillarRotCos;',
      'uniform float uPillarRotSin;',
      'uniform float uWaveSin;',
      'uniform float uWaveCos;',
      'varying vec2 vUv;',
      '',
      'const float STEP_MULT = ' + settings.stepMultiplier.toFixed(1) + ';',
      'const int MAX_ITER = ' + settings.iterations + ';',
      'const int WAVE_ITER = ' + settings.waveIterations + ';',
      '',

      'vec3 vhTanh(vec3 x) {',
      '  x = clamp(x, -8.0, 8.0);',
      '  vec3 e = exp(2.0 * x);',
      '  return (e - 1.0) / (e + 1.0);',
      '}',
      '',
      'void main() {',
      '  vec2 uv = (vUv * 2.0 - 1.0) * vec2(uResolution.x / uResolution.y, 1.0);',
      '  uv = vec2(uPillarRotCos * uv.x - uPillarRotSin * uv.y, uPillarRotSin * uv.x + uPillarRotCos * uv.y);',
      '',
      '  vec3 ro = vec3(0.0, 0.0, -10.0);',
      '  vec3 rd = normalize(vec3(uv, 1.0));',
      '',
      '  float rotC = uRotCos;',
      '  float rotS = uRotSin;',
      '  if(uInteractive && (uMouse.x != 0.0 || uMouse.y != 0.0)) {',
      '    float a = uMouse.x * 6.283185;',
      '    rotC = cos(a);',
      '    rotS = sin(a);',
      '  }',
      '',
      '  vec3 col = vec3(0.0);',
      '  float t = 0.1;',
      '',
      '  for(int i = 0; i < MAX_ITER; i++) {',
      '    vec3 p = ro + rd * t;',
      '    p.xz = vec2(rotC * p.x - rotS * p.z, rotS * p.x + rotC * p.z);',
      '',
      '    vec3 q = p;',
      '    q.y = p.y * uPillarHeight + uTime;',
      '',
      '    float freq = 1.0;',
      '    float amp = 1.0;',
      '    for(int j = 0; j < WAVE_ITER; j++) {',
      '      q.xz = vec2(uWaveCos * q.x - uWaveSin * q.z, uWaveSin * q.x + uWaveCos * q.z);',
      '      q += cos(q.zxy * freq - uTime * float(j) * 2.0) * amp;',
      '      freq *= 2.0;',
      '      amp *= 0.5;',
      '    }',
      '',
      '    float d = length(cos(q.xz)) - 0.2;',
      '    float bound = length(p.xz) - uPillarWidth;',
      '    float k = 4.0;',
      '    float h = max(k - abs(d - bound), 0.0);',
      '    d = max(d, bound) + h * h * 0.0625 / k;',
      '    d = abs(d) * 0.15 + 0.01;',
      '',
      '    float grad = clamp((15.0 - p.y) / 30.0, 0.0, 1.0);',
      '    col += mix(uBottomColor, uTopColor, grad) / d;',
      '',
      '    t += d * STEP_MULT;',
      '    if(t > 50.0) break;',
      '  }',
      '',
      '  float widthNorm = uPillarWidth / 3.0;',
      '  col = vhTanh(col * uGlowAmount / widthNorm);',
      '',
      '  col -= fract(sin(dot(gl_FragCoord.xy, vec2(12.9898, 78.233))) * 43758.5453) / 15.0 * uNoiseIntensity;',
      '',
      '  gl_FragColor = vec4(col * uIntensity, 1.0);',
      '}'
    ].join('\n');
  }

  var QUALITY = {
    low:    { iterations: 24, waveIterations: 1, pixelRatio: 0.5,  precision: 'mediump', stepMultiplier: 1.5 },
    medium: { iterations: 40, waveIterations: 2, pixelRatio: 0.65, precision: 'mediump', stepMultiplier: 1.2 },
    high:   { iterations: 80, waveIterations: 4, pixelRatio: Math.min(window.devicePixelRatio || 1, 2), precision: 'highp', stepMultiplier: 1.0 }
  };

  function hexToRGB(hex) {
    hex = String(hex || '').replace('#', '');
    if (hex.length === 3) hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
    return [
      parseInt(hex.slice(0, 2), 16) / 255,
      parseInt(hex.slice(2, 4), 16) / 255,
      parseInt(hex.slice(4, 6), 16) / 255
    ];
  }

  function compile(gl, type, src) {
    var sh = gl.createShader(type);
    gl.shaderSource(sh, src);
    gl.compileShader(sh);
    if (!gl.getShaderParameter(sh, gl.COMPILE_STATUS)) {
      console.error('[light-pillar] shader compile failed:', gl.getShaderInfoLog(sh));
      gl.deleteShader(sh);
      return null;
    }
    return sh;
  }

  function mount(canvas, opts) {
    opts = opts || {};

    var ua = navigator.userAgent;
    var isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(ua);
    var isLowEnd = isMobile || (navigator.hardwareConcurrency && navigator.hardwareConcurrency <= 4);

    var quality = opts.quality || 'high';
    if (isLowEnd && quality === 'high') quality = 'medium';
    if (isMobile && quality !== 'low') quality = 'low';
    var settings = QUALITY[quality] || QUALITY.medium;

    var glOpts = {
      alpha: true,
      antialias: false,
      depth: false,
      stencil: false,
      powerPreference: quality === 'high' ? 'high-performance' : 'low-power'
    };
    var gl = canvas.getContext('webgl', glOpts) || canvas.getContext('experimental-webgl', glOpts);
    if (!gl) return null;

    var vs = compile(gl, gl.VERTEX_SHADER, VERT);
    var fs = compile(gl, gl.FRAGMENT_SHADER, buildFragment(settings));
    if (!vs || !fs) return null;

    var prog = gl.createProgram();
    gl.attachShader(prog, vs);
    gl.attachShader(prog, fs);
    gl.linkProgram(prog);
    if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) {
      console.error('[light-pillar] program link failed:', gl.getProgramInfoLog(prog));
      return null;
    }
    gl.useProgram(prog);

    var buf = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, buf);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 3, -1, -1, 3]), gl.STATIC_DRAW);
    var aPos = gl.getAttribLocation(prog, 'aPos');
    gl.enableVertexAttribArray(aPos);
    gl.vertexAttribPointer(aPos, 2, gl.FLOAT, false, 0, 0);

    function loc(name) { return gl.getUniformLocation(prog, name); }
    var u = {
      time: loc('uTime'), res: loc('uResolution'), mouse: loc('uMouse'),
      top: loc('uTopColor'), bottom: loc('uBottomColor'),
      intensity: loc('uIntensity'), interactive: loc('uInteractive'),
      glow: loc('uGlowAmount'), pw: loc('uPillarWidth'), ph: loc('uPillarHeight'),
      noise: loc('uNoiseIntensity'),
      rotCos: loc('uRotCos'), rotSin: loc('uRotSin'),
      pRotCos: loc('uPillarRotCos'), pRotSin: loc('uPillarRotSin'),
      waveSin: loc('uWaveSin'), waveCos: loc('uWaveCos')
    };

    var top = hexToRGB(opts.topColor || '#5227FF');
    var bottom = hexToRGB(opts.bottomColor || '#FF9FFC');
    var pillarRotRad = ((opts.pillarRotation || 0) * Math.PI) / 180;
    var interactive = !!opts.interactive;
    var rotationSpeed = opts.rotationSpeed == null ? 0.3 : opts.rotationSpeed;

    gl.uniform3f(u.top, top[0], top[1], top[2]);
    gl.uniform3f(u.bottom, bottom[0], bottom[1], bottom[2]);
    gl.uniform1f(u.intensity, opts.intensity == null ? 1 : opts.intensity);
    gl.uniform1i(u.interactive, interactive ? 1 : 0);
    gl.uniform1f(u.glow, opts.glowAmount == null ? 0.005 : opts.glowAmount);
    gl.uniform1f(u.pw, opts.pillarWidth == null ? 3 : opts.pillarWidth);
    gl.uniform1f(u.ph, opts.pillarHeight == null ? 0.4 : opts.pillarHeight);
    gl.uniform1f(u.noise, opts.noiseIntensity == null ? 0.5 : opts.noiseIntensity);
    gl.uniform1f(u.pRotCos, Math.cos(pillarRotRad));
    gl.uniform1f(u.pRotSin, Math.sin(pillarRotRad));
    // Fixed 0.4rad wave rotation, as in the original.
    gl.uniform1f(u.waveSin, Math.sin(0.4));
    gl.uniform1f(u.waveCos, Math.cos(0.4));
    gl.uniform2f(u.mouse, 0, 0);

    function resize() {
      var w = Math.max(1, Math.floor(canvas.clientWidth * settings.pixelRatio));
      var h = Math.max(1, Math.floor(canvas.clientHeight * settings.pixelRatio));
      if (canvas.width !== w || canvas.height !== h) {
        canvas.width = w;
        canvas.height = h;
        gl.viewport(0, 0, w, h);
        gl.uniform2f(u.res, w, h);
      }
    }

    if (interactive) {
      var throttled = false;
      canvas.addEventListener('mousemove', function (e) {
        if (throttled) return;
        throttled = true;
        setTimeout(function () { throttled = false; }, 16);
        var r = canvas.getBoundingClientRect();
        gl.uniform2f(
          u.mouse,
          ((e.clientX - r.left) / r.width) * 2 - 1,
          -((e.clientY - r.top) / r.height) * 2 + 1
        );
      }, { passive: true });
    }

    var time = 0;
    var raf = null;
    var running = false;
    var lastFrame = 0;
    var frameTime = 1000 / (quality === 'low' ? 30 : 60);

    function draw() {
      resize();
      gl.uniform1f(u.time, time);
      gl.uniform1f(u.rotCos, Math.cos(time * 0.3));
      gl.uniform1f(u.rotSin, Math.sin(time * 0.3));
      gl.drawArrays(gl.TRIANGLES, 0, 3);
    }

    function frame(now) {
      if (!running) return;
      var delta = now - lastFrame;
      if (delta >= frameTime) {
        time += 0.016 * rotationSpeed;
        draw();
        lastFrame = now - (delta % frameTime);
      }
      raf = requestAnimationFrame(frame);
    }

    function start() {
      if (running) return;
      running = true;
      lastFrame = 0;
      raf = requestAnimationFrame(frame);
    }

    function stop() {
      running = false;
      if (raf) cancelAnimationFrame(raf);
      raf = null;
    }

    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduce) {
      resize();
      draw();
    } else {
      start();
    }

    var resizeTimer = null;
    window.addEventListener('resize', function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () {
        resize();
        if (!running) draw();
      }, 150);
    }, { passive: true });

    document.addEventListener('visibilitychange', function () {
      if (reduce) return;
      if (document.hidden) stop(); else start();
    });

    return { start: start, stop: stop, canvas: canvas, quality: quality };
  }

  function num(el, attr, fallback) {
    var v = parseFloat(el.getAttribute(attr));
    return isNaN(v) ? fallback : v;
  }

  function autoInit() {
    var nodes = document.querySelectorAll('canvas[data-light-pillar]');
    for (var i = 0; i < nodes.length; i++) {
      var el = nodes[i];
      var instance = mount(el, {
        topColor: el.getAttribute('data-top-color') || '#5227FF',
        bottomColor: el.getAttribute('data-bottom-color') || '#FF9FFC',
        intensity: num(el, 'data-intensity', 1),
        rotationSpeed: num(el, 'data-rotation-speed', 0.3),
        glowAmount: num(el, 'data-glow', 0.005),
        pillarWidth: num(el, 'data-pillar-width', 3),
        pillarHeight: num(el, 'data-pillar-height', 0.4),
        noiseIntensity: num(el, 'data-noise', 0.5),
        pillarRotation: num(el, 'data-pillar-rotation', 0),
        interactive: el.getAttribute('data-interactive') === 'true',
        quality: el.getAttribute('data-quality') || 'high'
      });

      if (!instance && el.parentNode) el.parentNode.removeChild(el);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', autoInit);
  } else {
    autoInit();
  }

  window.VHLightPillar = { mount: mount };
})();
