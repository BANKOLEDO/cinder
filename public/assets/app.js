(() => {
  "use strict";

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* ---------- toast ---------- */

  let toastTimer = 0;
  function toast(message) {
    let el = $(".toast");
    if (!el) {
      el = document.createElement("div");
      el.className = "toast";
      el.setAttribute("role", "status");
      el.setAttribute("aria-live", "polite");
      document.body.appendChild(el);
    }
    el.textContent = message;
    el.classList.add("is-on");
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => el.classList.remove("is-on"), 2200);
  }

  /* ---------- clipboard ---------- */

  async function copyText(text) {
    try {
      await navigator.clipboard.writeText(text);
      return true;
    } catch {
      const helper = document.createElement("textarea");
      helper.className = "clip-helper";
      helper.value = text;
      helper.setAttribute("readonly", "");
      document.body.appendChild(helper);
      helper.select();
      let ok = false;
      try {
        ok = document.execCommand("copy");
      } catch {
        ok = false;
      }
      helper.remove();
      return ok;
    }
  }

  /* ---------- character count ---------- */

  const byteLength = (value) => new TextEncoder().encode(value).length;

  $$("[data-charcount]").forEach((out) => {
    const field = $("#body");
    if (!field) return;
    const max = Number(field.getAttribute("maxlength")) || 32768;
    const tick = () => {
      const n = byteLength(field.value);
      out.textContent = n.toLocaleString("en-US") + " / " + max.toLocaleString("en-US") + " bytes";
      out.classList.toggle("is-near", n > max * 0.9);
    };
    field.addEventListener("input", tick);
    tick();
  });

  /* ---------- absolutize + copy buttons ---------- */

  $$("input[data-absolutize]").forEach((input) => {
    input.value = new URL(input.value, window.location.origin).href;
  });

  $$("[data-copy-target]").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const field = document.getElementById(btn.getAttribute("data-copy-target"));
      if (!field) return;
      const text = field.value !== undefined ? field.value : field.textContent;
      const ok = await copyText(text.trim());
      const label = btn.querySelector(".btn-label");
      const target = label || btn;
      const original = target.textContent;
      target.textContent = ok ? "Copied" : "Select & copy";
      if (!ok && field.select) field.select();
      window.setTimeout(() => {
        target.textContent = original;
      }, 1600);
      toast(ok ? "Copied to clipboard" : "Select and copy manually");
    });
  });

  /* ---------- countdown ---------- */

  $$("[data-countdown]").forEach((out) => {
    const expires = Number(out.getAttribute("data-expires")) * 1000;
    if (!expires) return;
    const pad = (n) => String(n).padStart(2, "0");
    const tick = () => {
      const diff = expires - Date.now();
      if (diff <= 0) {
        out.textContent = "Expired";
        out.classList.add("is-expired");
        window.clearInterval(timer);
        return;
      }
      const s = Math.floor(diff / 1000);
      const d = Math.floor(s / 86400);
      const h = Math.floor((s % 86400) / 3600);
      const m = Math.floor((s % 3600) / 60);
      if (d > 0) out.textContent = d + "d " + pad(h) + "h " + pad(m) + "m";
      else if (h > 0) out.textContent = pad(h) + "h " + pad(m) + "m " + pad(s % 60) + "s";
      else out.textContent = pad(m) + "m " + pad(s % 60) + "s";
    };
    const timer = window.setInterval(tick, 1000);
    tick();
  });

  /* ---------- passphrase strength + generator ---------- */

  const pw = $("#password");
  const meter = $("[data-meter]");
  const meterText = $("[data-meter-text]");

  function strength(value) {
    if (!value) return { level: 0, pct: 0, label: "", hint: "Add " + 10 + "+ characters" };
    let pool = 0;
    if (/[a-z]/.test(value)) pool += 26;
    if (/[A-Z]/.test(value)) pool += 26;
    if (/[0-9]/.test(value)) pool += 10;
    if (/[^A-Za-z0-9]/.test(value)) pool += 33;
    const bits = value.length * Math.log2(Math.max(pool, 2));
    if (bits < 50) return { level: 1, pct: 26, label: "Weak", hint: "" };
    if (bits < 80) return { level: 2, pct: 56, label: "Fair", hint: "" };
    if (bits < 120) return { level: 3, pct: 82, label: "Strong", hint: "" };
    return { level: 4, pct: 100, label: "Very strong", hint: "" };
  }

  function paintStrength() {
    if (!pw || !meter) return;
    const s = strength(pw.value);
    meter.setAttribute("data-level", String(s.level));
    if (meterText) {
      meterText.textContent = pw.value
        ? s.label + " \u00b7 " + pw.value.length + " characters"
        : s.hint;
    }
  }

  if (pw) {
    pw.addEventListener("input", paintStrength);
    paintStrength();
  }

  const generate = $("[data-generate]");
  if (generate) {
    generate.addEventListener("click", () => {
      const alphabet = "ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789";
      const limit = 256 - (256 % alphabet.length);
      const pick = () => {
        const buf = new Uint8Array(1);
        let n = 256;
        while (n >= limit) {
          crypto.getRandomValues(buf);
          n = buf[0];
        }
        return alphabet[n % alphabet.length];
      };
      const raw = Array.from({ length: 20 }, pick).join("");
      const grouped = raw.match(/.{1,4}/g).join("-");
      if (pw) {
        pw.value = grouped;
        pw.dispatchEvent(new Event("input", { bubbles: true }));
        pw.focus();
        pw.setSelectionRange(grouped.length, grouped.length);
      }
      toast("Passphrase generated");
    });
  }

  /* ---------- expiry preview ---------- */

  const ttlInputs = $$('input[name="ttl"]');
  const expiryPreview = $("[data-expiry-preview]");
  if (ttlInputs.length && expiryPreview) {
    const fmt = new Intl.DateTimeFormat(undefined, { dateStyle: "medium", timeStyle: "short" });
    const paint = () => {
      const selected = ttlInputs.find((el) => el.checked);
      const seconds = selected ? Number(selected.value) : 0;
      if (!seconds) return;
      const when = new Date(Date.now() + seconds * 1000);
      expiryPreview.textContent = "Expires " + fmt.format(when);
    };
    ttlInputs.forEach((el) => el.addEventListener("change", paint));
    paint();
  }

  /* ---------- read limit preview ---------- */

  const readsInputs = $$('input[name="reads"]');
  const readsPreview = $("[data-reads-preview]");
  if (readsInputs.length && readsPreview) {
    const readsMeta = $("[data-reads-meta]");
    const readsSummary = $("[data-reads-summary]");
    const readsChip = $("[data-reads-chip]");
    const paintReads = () => {
      const selected = readsInputs.find((el) => el.checked);
      const count = selected ? Number(selected.value) : 1;
      const label = count === 1 ? "1 read" : count + " reads";
      const allowed = count === 1 ? "1 allowed" : count + " allowed";
      readsPreview.textContent = label;
      if (readsMeta) readsMeta.textContent = label;
      if (readsSummary) readsSummary.textContent = allowed;
      if (readsChip) readsChip.textContent = label;
    };
    readsInputs.forEach((el) => el.addEventListener("change", paintReads));
    paintReads();
  }

  /* ---------- show / hide passphrase ---------- */

  $$("[data-toggle-pw]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const field = document.getElementById("password");
      if (!field) return;
      const show = field.type === "password";
      field.type = show ? "text" : "password";
      btn.textContent = show ? "Hide" : "Show";
      btn.setAttribute("aria-pressed", show ? "true" : "false");
    });
  });

  /* ---------- seal state ---------- */

  const form = $("form.compose-grid");
  if (form) {
    form.addEventListener("submit", () => {
      const btn = $(".btn-seal", form);
      if (!btn) return;
      btn.disabled = true;
      const label = btn.querySelector(".btn-label");
      if (label) label.textContent = "Sealing\u2026";
      else btn.textContent = "Sealing\u2026";
    });
  }

  /* ---------- Ctrl/Cmd+Enter ---------- */

  document.addEventListener("keydown", (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key === "Enter") {
      const active = document.activeElement;
      const owner = active && active.form ? active.form : $("form.compose-grid") || $(".panel-body form");
      if (owner) {
        event.preventDefault();
        if (owner.requestSubmit) owner.requestSubmit();
      }
    }
  });

  /* ---------- command palette ---------- */

  const palette = $("[data-palette]");
  const paletteInput = $("[data-palette-input]");
  const paletteList = $("[data-palette-list]");
  const paletteEmpty = $("[data-palette-empty]");

  const commands = [];
  const add = (label, group, run) => commands.push({ label, group, run });

  if ($("form.compose-grid")) {
    add("Focus the editor", "Compose", () => { const el = $("#body"); if (el) el.focus(); });
    add("Generate a passphrase", "Compose", () => { const el = $("[data-generate]"); if (el) el.click(); });
    add("Seal this note", "Compose", () => { const el = $("form.compose-grid"); if (el && el.requestSubmit) el.requestSubmit(); });
    add("Clear the editor", "Compose", () => {
      const el = $("#body");
      if (!el) return;
      el.value = "";
      el.dispatchEvent(new Event("input", { bubbles: true }));
      el.focus();
    });
  }
  if ($("[data-copy-target='note-link']")) {
    add("Copy the secret link", "Share", () => { const el = $("[data-copy-target='note-link']"); if (el) el.click(); });
    add("Open this note in this tab", "Share", () => {
      const el = $("#note-link");
      if (el) window.location.href = el.value;
    });
  }
  if ($("[data-copy-target='note-body']")) {
    add("Copy the note to clipboard", "Share", () => { const el = $("[data-copy-target='note-body']"); if (el) el.click(); });
  }
  if ($("[data-toggle-pw]")) {
    add("Show or hide the passphrase", "Read", () => { const el = $("[data-toggle-pw]"); if (el) el.click(); });
  }
  add("New note", "Navigate", () => { window.location.href = "/"; });
  add("Your notes", "Navigate", () => { window.location.href = "/history"; });
  add("Security model", "Navigate", () => { window.location.href = "/security"; });

  let filtered = [];
  let active = 0;
  let isOpen = false;

  function renderList() {
    if (!paletteList) return;
    paletteList.innerHTML = "";
    filtered.forEach((cmd, i) => {
      const li = document.createElement("li");
      li.className = "palette-item" + (i === active ? " is-active" : "");
      li.setAttribute("role", "option");
      li.setAttribute("aria-selected", i === active ? "true" : "false");

      const label = document.createElement("span");
      label.className = "palette-label";
      label.textContent = cmd.label;

      const group = document.createElement("span");
      group.className = "palette-group";
      group.textContent = cmd.group;

      li.append(label, group);
      li.addEventListener("mousedown", (event) => {
        event.preventDefault();
        runCommand(i);
      });
      paletteList.appendChild(li);
    });
    if (paletteEmpty) paletteEmpty.hidden = filtered.length > 0;
  }

  function filter() {
    const q = (paletteInput.value || "").trim().toLowerCase();
    filtered = commands.filter((cmd) =>
      !q || cmd.label.toLowerCase().includes(q) || cmd.group.toLowerCase().includes(q)
    );
    active = 0;
    renderList();
  }

  function openPalette() {
    if (!palette || isOpen) return;
    isOpen = true;
    palette.hidden = false;
    document.body.classList.add("palette-open");
    paletteInput.value = "";
    filter();
    paletteInput.focus();
  }

  function closePalette() {
    if (!palette || !isOpen) return;
    isOpen = false;
    palette.hidden = true;
    document.body.classList.remove("palette-open");
  }

  function runCommand(i) {
    const cmd = filtered[i];
    if (!cmd) return;
    closePalette();
    cmd.run();
  }

  $$("[data-cmdk]").forEach((btn) => btn.addEventListener("click", openPalette));

  if (palette) {
    document.addEventListener("keydown", (event) => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k") {
        event.preventDefault();
        isOpen ? closePalette() : openPalette();
      }
    });

    palette.addEventListener("mousedown", (event) => {
      if (event.target === palette) closePalette();
    });

    paletteInput.addEventListener("input", filter);

    paletteInput.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        event.preventDefault();
        closePalette();
      } else if (event.key === "ArrowDown") {
        event.preventDefault();
        active = Math.min(active + 1, filtered.length - 1);
        renderList();
        const el = paletteList.children[active];
        if (el) el.scrollIntoView({ block: "nearest" });
      } else if (event.key === "ArrowUp") {
        event.preventDefault();
        active = Math.max(active - 1, 0);
        renderList();
        const el = paletteList.children[active];
        if (el) el.scrollIntoView({ block: "nearest" });
      } else if (event.key === "Enter") {
        event.preventDefault();
        runCommand(active);
      }
    });
  }

  /* ---------- hero WebGL ember ---------- */

  const glCanvas = $("canvas[data-gl]");
  if (glCanvas) {
    const gl = glCanvas.getContext("webgl", {
      antialias: false,
      alpha: false,
      depth: false,
      powerPreference: "low-power",
    });

    if (gl) {
      const vertex = "attribute vec2 p;void main(){gl_Position=vec4(p,0.0,1.0);}";
      const fragment = [
        "precision mediump float;",
        "uniform vec2 u_res;",
        "uniform float u_time;",
        "float hash(vec2 p){return fract(sin(dot(p,vec2(127.1,311.7)))*43758.5453123);}",
        "float noise(vec2 p){",
        "  vec2 i=floor(p),f=fract(p);",
        "  f=f*f*(3.0-2.0*f);",
        "  float a=hash(i),b=hash(i+vec2(1.0,0.0));",
        "  float c=hash(i+vec2(0.0,1.0)),d=hash(i+vec2(1.0,1.0));",
        "  return mix(mix(a,b,f.x),mix(c,d,f.x),f.y);",
        "}",
        "float fbm(vec2 p){",
        "  float v=0.0,a=0.5;",
        "  for(int i=0;i<4;i++){v+=a*noise(p);p=p*2.03+7.1;a*=0.5;}",
        "  return v;",
        "}",
        "void main(){",
        "  vec2 uv=gl_FragCoord.xy/u_res;",
        "  vec2 p=uv;",
        "  p.x*=u_res.x/u_res.y;",
        "  float t=u_time*0.035;",
        "  float n=fbm(p*2.2+vec2(t,-t*0.6));",
        "  float n2=fbm(p*5.0-vec2(t*0.7,t*0.4));",
        "  float field=mix(n,n2,0.42);",
        "  float gx=exp(-pow((uv.x-0.5)*2.0,2.0));",
        "  float gy=exp(-pow((uv.y-0.44)*1.9,2.0));",
        "  float mask=smoothstep(0.3,0.92,field)*(0.28+0.72*gx*gy);",
        "  vec3 col=vec3(0.039)+vec3(1.0,0.34,0.2)*mask*0.46;",
        "  float vig=smoothstep(1.15,0.25,length(uv-vec2(0.5,0.5)));",
        "  col*=0.72+0.28*vig;",
        "  col+=(hash(gl_FragCoord.xy+u_time)-0.5)*0.018;",
        "  gl_FragColor=vec4(col,1.0);",
        "}",
      ].join("\n");

      const compile = (type, source) => {
        const shader = gl.createShader(type);
        if (!shader) return null;
        gl.shaderSource(shader, source);
        gl.compileShader(shader);
        return gl.getShaderParameter(shader, gl.COMPILE_STATUS) ? shader : null;
      };

      const vs = compile(gl.VERTEX_SHADER, vertex);
      const fs = compile(gl.FRAGMENT_SHADER, fragment);
      const program = vs && fs ? gl.createProgram() : null;

      if (program && vs && fs) {
        gl.attachShader(program, vs);
        gl.attachShader(program, fs);
        gl.linkProgram(program);

        if (gl.getProgramParameter(program, gl.LINK_STATUS)) {
          gl.useProgram(program);

          const buffer = gl.createBuffer();
          gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
          gl.bufferData(
            gl.ARRAY_BUFFER,
            new Float32Array([-1, -1, 3, -1, -1, 3]),
            gl.STATIC_DRAW
          );

          const loc = gl.getAttribLocation(program, "p");
          gl.enableVertexAttribArray(loc);
          gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);

          const uRes = gl.getUniformLocation(program, "u_res");
          const uTime = gl.getUniformLocation(program, "u_time");

          let drawn = 0;
          let visible = true;
          let raf = 0;
          const start = performance.now();

          const resize = () => {
            const dpr = Math.min(window.devicePixelRatio || 1, 1.5);
            const w = Math.max(1, Math.round(glCanvas.clientWidth * dpr));
            const h = Math.max(1, Math.round(glCanvas.clientHeight * dpr));
            if (glCanvas.width !== w || glCanvas.height !== h) {
              glCanvas.width = w;
              glCanvas.height = h;
            }
            gl.viewport(0, 0, glCanvas.width, glCanvas.height);
            gl.uniform2f(uRes, glCanvas.width, glCanvas.height);
            drawn = 0;
          };

          const draw = (seconds) => {
            gl.uniform1f(uTime, seconds);
            gl.drawArrays(gl.TRIANGLES, 0, 3);
            drawn = seconds;
          };

          const frame = (now) => {
            if (!visible) return;
            draw((now - start) / 1000);
            raf = requestAnimationFrame(frame);
          };

          const stop = () => {
            if (raf) cancelAnimationFrame(raf);
            raf = 0;
          };

          const begin = () => {
            stop();
            if (reduceMotion) {
              if (drawn === 0) draw(0.6);
              return;
            }
            if (!raf && visible) raf = requestAnimationFrame(frame);
          };

          resize();
          begin();

          window.addEventListener("resize", () => {
            resize();
            if (reduceMotion || !visible) draw(drawn || 0.6);
          });

          document.addEventListener("visibilitychange", () => {
            visible = !document.hidden;
            if (visible) begin();
            else stop();
          });

          if ("IntersectionObserver" in window) {
            new IntersectionObserver(
              (entries) => {
                visible = entries[0].isIntersecting && !document.hidden;
                if (visible) begin();
                else stop();
              },
              { threshold: 0 }
            ).observe(glCanvas);
          }
        }
      }
    }
  }

  /* ---------- subtle entrance ---------- */

  if (!reduceMotion) {
    document.documentElement.classList.add("is-ready");
  }
})();
