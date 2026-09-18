
(function () {
  'use strict';

  var DEFAULTS = {
    horizon: '#5227FF',      // data-gw-horizon   — distant haze the waves fade into
    wave: '#FF9FFC',         // data-gw-wave      — mid colour of the wave bodies
    crest: '#FFFFFF',        // data-gw-crest     — highlight on the nearest crests
    speed: 0.4,              // data-gw-speed
    amplitude: 2.5,          // data-gw-amplitude
    waveScale: 0.6,          // data-gw-wave-scale
    waveRatio: 0.9,          // data-gw-wave-ratio
    swell: 35,               // data-gw-swell
    turbulence: 20,          // data-gw-turbulence
    tilt: 1.11,              // data-gw-tilt      — camera pitch, radians
    zoom: 1.0,               // data-gw-zoom
    height: 5.5,             // data-gw-height    — vertical offset of the horizon
    fogDepth: 15,            // data-gw-fog-depth
    detail: 'medium',        // data-gw-detail    — low | medium | high
    brightness: 1.0,         // data-gw-brightness
    opacity: 1.0,            // data-gw-opacity
    mouseInteraction: true,  // data-gw-mouse     — "false" disables
    parallaxStrength: 0.5,   // data-gw-parallax
    grain: true,             // data-gw-grain     — "false" disables
    grainIntensity: 0.05     // data-gw-grain-intensity
  };

  var VERTEX = [
    '#version 300 es',
    'in vec2 position;',
    'void main() {',
    '  gl_Position = vec4(position, 0.0, 1.0);',
    '}'
  ].join('\n');

  var FRAGMENT = [
    '#version 300 es',
    'precision highp float;',
    'uniform vec2 iResolution;',
    'uniform float iTime;',
    'uniform float uSpeed;',
    'uniform float uAmplitude;',
    'uniform float uWaveScale;',
    'uniform float uWaveRatio;',
    'uniform float uSwell;',
    'uniform float uTurbulence;',
    'uniform float uTilt;',
    'uniform float uZoom;',
    'uniform float uHeight;',
    'uniform float uFogDepth;',
    'uniform float uSteps;',
    'uniform float uBrightness;',
    'uniform float uOpacity;',
    'uniform float uGrain;',
    'uniform float uGrainIntensity;',
    'uniform vec2 uMouse;',
    'uniform float uParallax;',
    'uniform bool uEnableMouse;',
    'uniform vec3 uHorizonColor;',
    'uniform vec3 uWaveColor;',
    'uniform vec3 uCrestColor;',
    'out vec4 fragColor;',
    '',
    'const float MAX_DIST = 20000.0;',
    '',
    'float hash21(vec2 p) {',
    '  vec3 p3 = fract(vec3(p.xyx) * 0.1031);',
    '  p3 += dot(p3, p3.yzx + 33.33);',
    '  return fract((p3.x + p3.y) * p3.z);',
    '}',
    '',
    'float plasma(vec3 r, vec2 freq, vec4 tc) {',
    '  float mx = r.x + tc.x;',
    '  mx += uSwell * sin((r.y + mx) / 20.0 + tc.y);',
    '  float my = r.y - tc.z;',
    '  my += uTurbulence * cos(r.x / 23.0 + tc.w);',
    '  return r.z - (sin(mx * freq.x) * uAmplitude + sin(my * freq.y) * uAmplitude + uHeight);',
    '}',
    '',
    'float raymarch(vec3 pos, vec3 dir, vec2 freq, vec4 tc) {',
    '  float dist = 0.0;',
    '  for (int i = 0; i < 128; i++) {',
    '    if (float(i) >= uSteps) break;',
    '    float dscene = plasma(pos + dist * dir, freq, tc);',
    '    if (abs(dscene) < 0.1) break;',
    '    dist += 0.9 * dscene;',
    '    if (!(abs(dist) < MAX_DIST)) return MAX_DIST;',
    '  }',
    '  return dist;',
    '}',
    '',
    'void main() {',
    '  float T = iTime * uSpeed;',
    '  vec2 freq = vec2(uWaveScale / 7.0, (uWaveScale * uWaveRatio) / 3.0);',
    '  vec4 tc = vec4(T / 0.130, T / 0.810, T / 0.200, T / 0.710);',
    '  float c, s;',
    '  float vfov = (3.14159 / 2.3) / max(uZoom, 0.05);',
    '  vec3 cam = vec3(0.0, 0.0, 30.0);',
    '  vec2 uv = (gl_FragCoord.xy / iResolution.xy) - 0.5;',
    '  uv.x *= iResolution.x / iResolution.y;',
    '  uv.y *= -1.0;',
    '',
    '  vec3 dir = vec3(0.0, 0.0, -1.0);',
    '  float ulen = length(uv);',
    '  float xrot = vfov * ulen;',
    '  c = cos(xrot); s = sin(xrot);',
    '  dir = mat3(1.0, 0.0, 0.0, 0.0, c, -s, 0.0, s, c) * dir;',
    '  vec2 nuv = ulen > 1e-5 ? uv / ulen : vec2(1.0, 0.0);',
    '  c = nuv.x; s = nuv.y;',
    '  dir = mat3(c, -s, 0.0, s, c, 0.0, 0.0, 0.0, 1.0) * dir;',
    '  c = cos(uTilt); s = sin(uTilt);',
    '  dir = mat3(c, 0.0, s, 0.0, 1.0, 0.0, -s, 0.0, c) * dir;',
    '',
    '  if (uEnableMouse) {',
    '    float yaw = (uMouse.x - 0.5) * uParallax * 0.4;',
    '    float pitch = (uMouse.y - 0.5) * uParallax * 0.4;',
    '    c = cos(yaw); s = sin(yaw);',
    '    dir = mat3(c, 0.0, s, 0.0, 1.0, 0.0, -s, 0.0, c) * dir;',
    '    c = cos(pitch); s = sin(pitch);',
    '    dir = mat3(1.0, 0.0, 0.0, 0.0, c, -s, 0.0, s, c) * dir;',
    '  }',
    '',
    '  float dist = raymarch(cam, dir, freq, tc);',
    '  vec3 pos = cam + dist * dir;',
    '',
    '  float t = clamp(uFogDepth / max(dist, 0.001), 0.0, 1.0);',
    '  vec3 body = mix(uWaveColor, uCrestColor, clamp(pos.z * 0.08 + 0.5, 0.0, 1.0));',
    '  vec3 col = mix(uHorizonColor, body, t);',
    '  col *= uBrightness;',
    '  col = clamp(col, 0.0, 1.0);',
    '',
    '  float alpha = clamp(t, 0.0, 1.0) * uOpacity;',
    '  if (uGrain > 0.5) {',
    '    float g = hash21(gl_FragCoord.xy + mod(iTime, 64.0) * 11.0);',
    '    alpha += (g - 0.5) * uGrainIntensity;',
    '  }',
    '  alpha = clamp(alpha, 0.0, 1.0);',
    '  fragColor = vec4(col * alpha, alpha);',
    '}'
  ].join('\n');

  var UNIFORM_NAMES = [
    'iResolution', 'iTime', 'uSpeed', 'uAmplitude', 'uWaveScale', 'uWaveRatio',
    'uSwell', 'uTurbulence', 'uTilt', 'uZoom', 'uHeight', 'uFogDepth', 'uSteps',
    'uBrightness', 'uOpacity', 'uGrain', 'uGrainIntensity', 'uMouse', 'uParallax',
    'uEnableMouse', 'uHorizonColor', 'uWaveColor', 'uCrestColor'
  ];

  function hexToRgb(hex) {
    var m = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(String(hex));
    if (!m) return [1, 1, 1];
    return [parseInt(m[1], 16) / 255, parseInt(m[2], 16) / 255, parseInt(m[3], 16) / 255];
  }

  function detailToSteps(detail) {
    if (detail === 'low') return 40.0;
    if (detail === 'high') return 110.0;
    return 70.0;
  }

  function readOptions(el) {
    var d = el.dataset;
    var num = function (raw, fallback) {
      if (raw === undefined || raw === '') return fallback;
      var v = parseFloat(raw);
      return isFinite(v) ? v : fallback;
    };
    var bool = function (raw, fallback) {
      if (raw === undefined || raw === '') return fallback;
      return raw !== 'false' && raw !== '0';
    };
    return {
      horizon: d.gwHorizon || DEFAULTS.horizon,
      wave: d.gwWave || DEFAULTS.wave,
      crest: d.gwCrest || DEFAULTS.crest,
      speed: num(d.gwSpeed, DEFAULTS.speed),
      amplitude: num(d.gwAmplitude, DEFAULTS.amplitude),
      waveScale: num(d.gwWaveScale, DEFAULTS.waveScale),
      waveRatio: num(d.gwWaveRatio, DEFAULTS.waveRatio),
      swell: num(d.gwSwell, DEFAULTS.swell),
      turbulence: num(d.gwTurbulence, DEFAULTS.turbulence),
      tilt: num(d.gwTilt, DEFAULTS.tilt),
      zoom: num(d.gwZoom, DEFAULTS.zoom),
      height: num(d.gwHeight, DEFAULTS.height),
      fogDepth: num(d.gwFogDepth, DEFAULTS.fogDepth),
      detail: d.gwDetail || DEFAULTS.detail,
      brightness: num(d.gwBrightness, DEFAULTS.brightness),
      opacity: num(d.gwOpacity, DEFAULTS.opacity),
      mouseInteraction: bool(d.gwMouse, DEFAULTS.mouseInteraction),
      parallaxStrength: num(d.gwParallax, DEFAULTS.parallaxStrength),
      grain: bool(d.gwGrain, DEFAULTS.grain),
      grainIntensity: num(d.gwGrainIntensity, DEFAULTS.grainIntensity)
    };
  }

  function compile(gl, type, src) {
    var sh = gl.createShader(type);
    gl.shaderSource(sh, src);
    gl.compileShader(sh);
    if (!gl.getShaderParameter(sh, gl.COMPILE_STATUS)) {
      console.error('GradientWaves shader:', gl.getShaderInfoLog(sh));
      gl.deleteShader(sh);
      return null;
    }
    return sh;
  }

  function initGradientWaves(container) {
    var opts = readOptions(container);
    var reduceMotion = window.matchMedia &&
      window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var canvas = document.createElement('canvas');
    canvas.className = 'gradient-waves-canvas';
    container.appendChild(canvas);

    var gl = canvas.getContext('webgl2', {
      alpha: true,
      premultipliedAlpha: true,
      antialias: false,
      depth: false,
      stencil: false
    });
    if (!gl) {                     // no WebGL2 — leave the container's own background
      canvas.remove();
      return;
    }

    var vs = compile(gl, gl.VERTEX_SHADER, VERTEX);
    var fs = compile(gl, gl.FRAGMENT_SHADER, FRAGMENT);
    if (!vs || !fs) { canvas.remove(); return; }

    var prog = gl.createProgram();
    gl.attachShader(prog, vs);
    gl.attachShader(prog, fs);
    gl.bindAttribLocation(prog, 0, 'position');
    gl.linkProgram(prog);
    if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) {
      console.error('GradientWaves link:', gl.getProgramInfoLog(prog));
      canvas.remove();
      return;
    }
    gl.useProgram(prog);

    var u = {};
    UNIFORM_NAMES.forEach(function (name) { u[name] = gl.getUniformLocation(prog, name); });

    var vao = gl.createVertexArray();
    gl.bindVertexArray(vao);
    var buf = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, buf);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 3, -1, -1, 3]), gl.STATIC_DRAW);
    gl.enableVertexAttribArray(0);
    gl.vertexAttribPointer(0, 2, gl.FLOAT, false, 0, 0);

    gl.clearColor(0, 0, 0, 0);
    gl.disable(gl.DEPTH_TEST);

    // Static uniforms — set once, no per-frame churn.
    var hz = hexToRgb(opts.horizon), wv = hexToRgb(opts.wave), cr = hexToRgb(opts.crest);
    gl.uniform3f(u.uHorizonColor, hz[0], hz[1], hz[2]);
    gl.uniform3f(u.uWaveColor, wv[0], wv[1], wv[2]);
    gl.uniform3f(u.uCrestColor, cr[0], cr[1], cr[2]);
    gl.uniform1f(u.uSpeed, opts.speed);
    gl.uniform1f(u.uAmplitude, opts.amplitude);
    gl.uniform1f(u.uWaveScale, opts.waveScale);
    gl.uniform1f(u.uWaveRatio, opts.waveRatio);
    gl.uniform1f(u.uSwell, opts.swell);
    gl.uniform1f(u.uTurbulence, opts.turbulence);
    gl.uniform1f(u.uTilt, opts.tilt);
    gl.uniform1f(u.uZoom, opts.zoom);
    gl.uniform1f(u.uHeight, opts.height);
    gl.uniform1f(u.uFogDepth, opts.fogDepth);
    gl.uniform1f(u.uSteps, detailToSteps(opts.detail));
    gl.uniform1f(u.uBrightness, opts.brightness);
    gl.uniform1f(u.uOpacity, opts.opacity);
    gl.uniform1f(u.uGrain, opts.grain && !reduceMotion ? 1.0 : 0.0);
    gl.uniform1f(u.uGrainIntensity, opts.grainIntensity);
    gl.uniform1f(u.uParallax, opts.parallaxStrength);

    var mouseOn = opts.mouseInteraction && !reduceMotion;
    gl.uniform1i(u.uEnableMouse, mouseOn ? 1 : 0);

    var dpr = Math.min(window.devicePixelRatio || 1, 2);

    function draw(timeSeconds) {
      gl.uniform1f(u.iTime, timeSeconds);
      gl.uniform2f(u.uMouse, current[0], current[1]);
      gl.clear(gl.COLOR_BUFFER_BIT);
      gl.drawArrays(gl.TRIANGLES, 0, 3);
    }

    function setSize() {
      var rect = container.getBoundingClientRect();
      var w = Math.max(1, Math.floor(rect.width * dpr));
      var h = Math.max(1, Math.floor(rect.height * dpr));
      if (canvas.width === w && canvas.height === h) return;
      canvas.width = w;
      canvas.height = h;
      gl.viewport(0, 0, w, h);
      gl.uniform2f(u.iResolution, w, h);
      draw(lastTime);
    }

    var current = [0.5, 0.5];
    var target = [0.5, 0.5];
    var lastTime = 0;

    function onPointerMove(e) {
      var rect = canvas.getBoundingClientRect();
      target[0] = (e.clientX - rect.left) / rect.width;
      target[1] = 1.0 - (e.clientY - rect.top) / rect.height;
    }
    function onPointerLeave() { target[0] = 0.5; target[1] = 0.5; }

    if (mouseOn) {
      canvas.addEventListener('pointermove', onPointerMove);
      canvas.addEventListener('pointerleave', onPointerLeave);
    }

    var ro = new ResizeObserver(setSize);
    ro.observe(container);
    setSize();

    if (reduceMotion) {
      draw(0);
      return;
    }

    var raf = 0;
    var visible = true;
    var pageVisible = !document.hidden;
    var t0 = performance.now();

    function loop(t) {
      lastTime = (t - t0) * 0.001;
      current[0] += 0.05 * (target[0] - current[0]);
      current[1] += 0.05 * (target[1] - current[1]);
      draw(lastTime);
      raf = requestAnimationFrame(loop);
    }
    function start() { if (visible && pageVisible && raf === 0) raf = requestAnimationFrame(loop); }
    function stop() { if (raf !== 0) { cancelAnimationFrame(raf); raf = 0; } }

    var io = new IntersectionObserver(function (entries) {
      visible = entries[0].isIntersecting;
      if (visible) start(); else stop();
    }, { threshold: 0 });
    io.observe(container);

    document.addEventListener('visibilitychange', function () {
      pageVisible = !document.hidden;
      if (pageVisible) start(); else stop();
    });

    start();
  }

  function boot() {
    var nodes = document.querySelectorAll('[data-gradient-waves]');
    for (var i = 0; i < nodes.length; i++) initGradientWaves(nodes[i]);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
