/* VENTURES HARBOR — SCANNER BACKGROUND (scanner-bg.js) */
(function () {
  'use strict';

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
    'uniform float uSweepSpeed;',
    'uniform float uSweepWidth;',
    'uniform float uSweepFalloff;',
    'uniform float uScale;',
    'uniform float uFrequency;',
    'uniform float uRipple;',
    'uniform float uBandDensity;',
    'uniform float uLineSharpness;',
    'uniform float uGlow;',
    'uniform float uColorSpread;',
    'uniform float uBrightness;',
    'uniform float uContrast;',
    'uniform float uSoftness;',
    'uniform float uVignette;',
    'uniform float uOpacity;',
    'uniform float uScanline;',
    'uniform float uGrain;',
    'uniform float uGrainIntensity;',
    'uniform float uDirection;',
    'uniform vec2 uMouse;',
    'uniform float uMouseEnabled;',
    'uniform float uMouseRadius;',
    'uniform float uMouseStrength;',
    'uniform float uMouseActive;',
    'uniform vec3 uColor1;',
    'uniform vec3 uColor2;',
    'uniform vec3 uColor3;',
    'out vec4 fragColor;',
    '',
    'const float TAU = 6.2831853;',
    '',
    'float signalField(vec2 p, float t) {',
    '  float w = sin(p.x * 1.3 + t * 0.7);',
    '  w += sin(p.y * 1.7 - t * 0.52) * 0.8;',
    '  w += sin((p.x + p.y) * 0.9 + t * 0.91) * 0.6;',
    '  w += sin((p.x - p.y) * 1.53 - t * 0.63) * 0.42;',
    '  return w * 0.35;',
    '}',
    '',
    'vec3 palette(float f) {',
    '  f = clamp(f, 0.0, 1.0);',
    '  f = pow(f, uContrast);',
    '  vec3 c = mix(uColor1, uColor2, smoothstep(0.08, 0.6, f));',
    '  return mix(c, uColor3, smoothstep(0.68, 1.0, f));',
    '}',
    '',
    'float scanBand(float x, float aa, float sharp) {',
    '  float v = mix(0.5, 0.5 + 0.5 * cos(x * TAU), aa);',
    '  return pow(v, sharp);',
    '}',
    '',
    'void main() {',
    '  float aspect = iResolution.x / iResolution.y;',
    '  vec2 uv0 = (gl_FragCoord.xy * 2.0 - iResolution.xy) / iResolution.y;',
    '  vec2 p = uv0 / max(uScale, 0.001);',
    '',
    '  float t = iTime * uSpeed;',
    '',
    '  float mouseBoost = 0.0;',
    '  if (uMouseEnabled > 0.5) {',
    '    vec2 mUv = vec2((uMouse.x * 2.0 - 1.0) * aspect, uMouse.y * 2.0 - 1.0);',
    '    vec2 md = uv0 - mUv;',
    '    float r = max(uMouseRadius, 0.001);',
    '    mouseBoost = exp(-dot(md, md) / (r * r)) * uMouseStrength * uMouseActive;',
    '  }',
    '',
    '  float axis;',
    '  if (uDirection < 0.5) axis = p.y;',
    '  else if (uDirection < 1.5) axis = p.x;',
    '  else axis = (p.x + p.y) * 0.70710678;',
    '',
    '  float sig = signalField(p * uFrequency, t);',
    '  float coord = axis + sig * uRipple;',
    '',
    '  float phase = coord / max(uSweepWidth, 0.05) - t * uSweepSpeed;',
    '  float sweep = pow(0.5 + 0.5 * cos(phase * TAU), max(uSweepFalloff, 0.1));',
    '',
    '  float lc = coord * uBandDensity;',
    '  float aa = 1.0 / (1.0 + uSoftness * fwidth(lc) * 3.0);',
    '  aa = clamp(aa * (1.0 + mouseBoost * 0.6), 0.0, 1.0);',
    '',
    '  float bodyBase = clamp(0.5 + 0.5 * sig, 0.0, 1.0);',
    '  float body = bodyBase * bodyBase * uGlow * sweep;',
    '',
    '  float sharp = max(uLineSharpness, 0.1);',
    '  float split = uColorSpread * 0.16;',
    '  float fr = clamp(scanBand(lc + split, aa, sharp) * sweep + body, 0.0, 1.0);',
    '  float fg = clamp(scanBand(lc, aa, sharp) * sweep + body, 0.0, 1.0);',
    '  float fb = clamp(scanBand(lc - split, aa, sharp) * sweep + body, 0.0, 1.0);',
    '',
    '  vec3 col = vec3(palette(fr).r, palette(fg).g, palette(fb).b);',
    '',
    '  float inten = (fr + fg + fb) * 0.3333333 * uBrightness;',
    '  inten *= 1.0 + mouseBoost * 0.9;',
    '',
    '  if (uScanline > 0.5) {',
    '    inten *= 1.0 - 0.18 * (0.5 + 0.5 * cos(gl_FragCoord.y * 1.7));',
    '  }',
    '',
    '  if (uGrain > 0.5) {',
    '    float g = fract(sin(dot(gl_FragCoord.xy, vec2(12.9898, 78.233)) + iTime) * 43758.5453);',
    '    inten += (g - 0.5) * uGrainIntensity;',
    '  }',
    '',
    '  inten *= clamp(1.0 - uVignette * smoothstep(0.55, 1.65, length(uv0)), 0.0, 1.0);',
    '  inten = clamp(inten, 0.0, 1.0);',
    '',
    '  float a = clamp(inten * uOpacity, 0.0, 1.0);',
    '  fragColor = vec4(clamp(col, 0.0, 1.0) * a, a);',
    '}'
  ].join('\n');

  /* Same defaults as the component's props table. */
  var DEFAULTS = {
    color1: '#5227FF',
    color2: '#FF9FFC',
    color3: '#FFFFFF',
    speed: 0.5,
    sweepSpeed: 0.25,
    sweepWidth: 1.6,
    sweepFalloff: 6,
    scale: 1.5,
    frequency: 2,
    ripple: 0.22,
    bandDensity: 11,
    lineSharpness: 5.5,
    glow: 0.22,
    scanDirection: 'vertical',
    colorSpread: 0.7,
    brightness: 1.0,
    contrast: 1.15,
    softness: 1.4,
    vignette: 0.45,
    scanline: true,
    grain: true,
    grainIntensity: 0.05,
    opacity: 1.0,
    mouseInteraction: true,
    mouseRadius: 0.5,
    mouseStrength: 0.5
  };

  function hexToRgb(hex) {
    var m = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(String(hex || ''));
    if (!m) return [1, 1, 1];
    return [parseInt(m[1], 16) / 255, parseInt(m[2], 16) / 255, parseInt(m[3], 16) / 255];
  }

  function directionToFloat(dir) {
    return dir === 'horizontal' ? 1.0 : dir === 'diagonal' ? 2.0 : 0.0;
  }

  function attrName(key) {
    return 'data-' + key.replace(/[A-Z]/g, function (c) { return '-' + c.toLowerCase(); });
  }

  function readOptions(el) {
    var out = {};
    Object.keys(DEFAULTS).forEach(function (key) {
      var def = DEFAULTS[key];
      var raw = el.getAttribute(attrName(key));
      if (raw === null) { out[key] = def; return; }
      if (typeof def === 'number') {
        var n = parseFloat(raw);
        out[key] = isNaN(n) ? def : n;
      } else if (typeof def === 'boolean') {
        out[key] = raw !== 'false' && raw !== '0';
      } else {
        out[key] = raw;
      }
    });
    return out;
  }

  function compile(gl, type, src) {
    var sh = gl.createShader(type);
    gl.shaderSource(sh, src);
    gl.compileShader(sh);
    if (!gl.getShaderParameter(sh, gl.COMPILE_STATUS)) {
      console.warn('[vh-scanner] shader compile failed:', gl.getShaderInfoLog(sh));
      gl.deleteShader(sh);
      return null;
    }
    return sh;
  }

  function mount(container, opts) {
    if (!container) return null;

    var canvas = document.createElement('canvas');

    var gl = canvas.getContext('webgl2', {
      alpha: true,
      premultipliedAlpha: true,
      antialias: false,
      depth: false,
      stencil: false,
      powerPreference: 'low-power'
    });
    if (!gl) return null;

    var vs = compile(gl, gl.VERTEX_SHADER, VERTEX);
    var fs = compile(gl, gl.FRAGMENT_SHADER, FRAGMENT);
    if (!vs || !fs) return null;

    var prog = gl.createProgram();
    gl.attachShader(prog, vs);
    gl.attachShader(prog, fs);
    gl.linkProgram(prog);
    if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) {
      console.warn('[vh-scanner] link failed:', gl.getProgramInfoLog(prog));
      return null;
    }
    gl.useProgram(prog);

    var vao = gl.createVertexArray();
    gl.bindVertexArray(vao);
    var buf = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, buf);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 3, -1, -1, 3]), gl.STATIC_DRAW);
    var loc = gl.getAttribLocation(prog, 'position');
    gl.enableVertexAttribArray(loc);
    gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);

    var U = {};
    [
      'iResolution', 'iTime', 'uSpeed', 'uSweepSpeed', 'uSweepWidth', 'uSweepFalloff',
      'uScale', 'uFrequency', 'uRipple', 'uBandDensity', 'uLineSharpness', 'uGlow',
      'uColorSpread', 'uBrightness', 'uContrast', 'uSoftness', 'uVignette', 'uOpacity',
      'uScanline', 'uGrain', 'uGrainIntensity', 'uDirection', 'uMouse', 'uMouseEnabled',
      'uMouseRadius', 'uMouseStrength', 'uMouseActive', 'uColor1', 'uColor2', 'uColor3'
    ].forEach(function (n) { U[n] = gl.getUniformLocation(prog, n); });

    gl.clearColor(0, 0, 0, 0);
    gl.enable(gl.BLEND);
    // Premultiplied source, so ONE rather than SRC_ALPHA.
    gl.blendFunc(gl.ONE, gl.ONE_MINUS_SRC_ALPHA);

    canvas.style.width = '100%';
    canvas.style.height = '100%';
    canvas.style.display = 'block';
    container.appendChild(canvas);

    function applyOptions(o) {
      gl.useProgram(prog);
      gl.uniform1f(U.uSpeed, o.speed);
      gl.uniform1f(U.uSweepSpeed, o.sweepSpeed);
      gl.uniform1f(U.uSweepWidth, o.sweepWidth);
      gl.uniform1f(U.uSweepFalloff, o.sweepFalloff);
      gl.uniform1f(U.uScale, o.scale);
      gl.uniform1f(U.uFrequency, o.frequency);
      gl.uniform1f(U.uRipple, o.ripple);
      gl.uniform1f(U.uBandDensity, o.bandDensity);
      gl.uniform1f(U.uLineSharpness, o.lineSharpness);
      gl.uniform1f(U.uGlow, o.glow);
      gl.uniform1f(U.uColorSpread, o.colorSpread);
      gl.uniform1f(U.uBrightness, o.brightness);
      gl.uniform1f(U.uContrast, o.contrast);
      gl.uniform1f(U.uSoftness, o.softness);
      gl.uniform1f(U.uVignette, o.vignette);
      gl.uniform1f(U.uOpacity, o.opacity);
      gl.uniform1f(U.uScanline, o.scanline ? 1 : 0);
      gl.uniform1f(U.uGrain, o.grain ? 1 : 0);
      gl.uniform1f(U.uGrainIntensity, o.grainIntensity);
      gl.uniform1f(U.uDirection, directionToFloat(o.scanDirection));
      gl.uniform1f(U.uMouseEnabled, o.mouseInteraction ? 1 : 0);
      gl.uniform1f(U.uMouseRadius, o.mouseRadius);
      gl.uniform1f(U.uMouseStrength, o.mouseStrength);
      gl.uniform3fv(U.uColor1, hexToRgb(o.color1));
      gl.uniform3fv(U.uColor2, hexToRgb(o.color2));
      gl.uniform3fv(U.uColor3, hexToRgb(o.color3));
    }
    applyOptions(opts);

    var maxDpr = window.matchMedia && window.matchMedia('(max-width: 900px)').matches ? 1 : 1.5;

    function setSize() {
      var rect = container.getBoundingClientRect();
      var dpr = Math.min(window.devicePixelRatio || 1, maxDpr);
      var w = Math.max(1, Math.floor(rect.width * dpr));
      var h = Math.max(1, Math.floor(rect.height * dpr));
      if (canvas.width === w && canvas.height === h) return;
      canvas.width = w;
      canvas.height = h;
      gl.viewport(0, 0, w, h);
      gl.useProgram(prog);
      gl.uniform2f(U.iResolution, w, h);
    }

    var ro = null;
    if (window.ResizeObserver) {
      ro = new ResizeObserver(setSize);
      ro.observe(container);
    } else {
      window.addEventListener('resize', setSize);
    }
    setSize();

    var curMouse = [0.5, 0.5];
    var tgtMouse = [0.5, 0.5];
    var active = 0;
    var tgtActive = 0;

    function onMove(e) {
      var rect = canvas.getBoundingClientRect();
      tgtMouse[0] = (e.clientX - rect.left) / rect.width;
      tgtMouse[1] = 1 - (e.clientY - rect.top) / rect.height;
      tgtActive = 1;
    }
    function onLeave() { tgtActive = 0; }

    var pointerHost = container.closest('.browse-hero') || container;
    if (opts.mouseInteraction) {
      pointerHost.addEventListener('mousemove', onMove);
      pointerHost.addEventListener('mouseleave', onLeave);
    }

    var raf = 0;
    var visible = true;
    var pageVisible = !document.hidden;
    var t0 = performance.now();

    function frame(now) {
      gl.useProgram(prog);
      gl.uniform1f(U.iTime, (now - t0) * 0.001);

      curMouse[0] += 0.05 * (tgtMouse[0] - curMouse[0]);
      curMouse[1] += 0.05 * (tgtMouse[1] - curMouse[1]);
      gl.uniform2f(U.uMouse, curMouse[0], curMouse[1]);
      active += 0.05 * (tgtActive - active);
      gl.uniform1f(U.uMouseActive, active);

      gl.clear(gl.COLOR_BUFFER_BIT);
      gl.bindVertexArray(vao);
      gl.drawArrays(gl.TRIANGLES, 0, 3);

      raf = requestAnimationFrame(frame);
    }
    function start() { if (visible && pageVisible && !raf) raf = requestAnimationFrame(frame); }
    function stop() { if (raf) { cancelAnimationFrame(raf); raf = 0; } }

    var io = null;
    if (window.IntersectionObserver) {
      io = new IntersectionObserver(function (entries) {
        visible = entries[0].isIntersecting;
        visible ? start() : stop();
      }, { threshold: 0 });
      io.observe(container);
    }

    function onVis() {
      pageVisible = !document.hidden;
      pageVisible ? start() : stop();
    }
    document.addEventListener('visibilitychange', onVis);
    start();

    return function destroy() {
      stop();
      if (ro) ro.disconnect(); else window.removeEventListener('resize', setSize);
      if (io) io.disconnect();
      document.removeEventListener('visibilitychange', onVis);
      pointerHost.removeEventListener('mousemove', onMove);
      pointerHost.removeEventListener('mouseleave', onLeave);
      if (canvas.parentNode) canvas.parentNode.removeChild(canvas);
      var lose = gl.getExtension('WEBGL_lose_context');
      if (lose) lose.loseContext();
    };
  }

  function boot() {
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    var nodes = document.querySelectorAll('[data-vh-scanner]');
    for (var i = 0; i < nodes.length; i++) {
      var el = nodes[i];
      var destroy = mount(el, readOptions(el));

      if (destroy) el.setAttribute('data-vh-scanner-ready', '1');
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  // Exposed for reuse on other pages / manual mounting.
  window.VHScanner = { mount: mount, defaults: DEFAULTS };
})();
