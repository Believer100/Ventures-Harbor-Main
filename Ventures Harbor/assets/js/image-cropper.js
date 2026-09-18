/* VENTURES HARBOR — IMAGE CROP MODAL (image-cropper.js) */

window.VH = window.VH || {};

VH.cropImage = function (file, options) {
    options = options || {};
    return new Promise((resolve) => {
        if (typeof Cropper === "undefined") {
            console.warn("Cropper.js not loaded — uploading original image without cropping.");
            resolve(file);
            return;
        }

        const aspectRatio = options.aspectRatio || NaN; // NaN = free crop
        const title = options.title || "Adjust image";

        const overlay = document.createElement("div");
        overlay.className = "modal-overlay vh-crop-overlay active";
        overlay.innerHTML = `
            <div class="modal" style="max-width:660px;width:94%;">
                <button class="modal-close" type="button" data-crop-cancel>&times;</button>
                <div class="modal-header"><h3 class="modal-title">${title}</h3></div>
                <div style="padding:0 1.25rem;">
                    <div class="vh-crop-stage" style="max-height:58vh;background:#0f172a;border-radius:10px;overflow:hidden;">
                        <img class="vh-crop-img" alt="" style="max-width:100%;display:block;"/>
                    </div>
                    <div style="display:flex;gap:0.4rem;align-items:center;justify-content:center;flex-wrap:wrap;margin:0.8rem 0 0.3rem;">
                        <button type="button" class="btn btn--secondary btn--sm" data-crop-zoomout title="Zoom out">&minus;</button>
                        <button type="button" class="btn btn--secondary btn--sm" data-crop-zoomin title="Zoom in">&plus;</button>
                        <button type="button" class="btn btn--secondary btn--sm" data-crop-rotate title="Rotate 90°">&#8635;</button>
                        <button type="button" class="btn btn--secondary btn--sm" data-crop-reset title="Reset">Reset</button>
                    </div>
                    <p style="font-size:0.75rem;color:#94a3b8;text-align:center;margin:0 0 0.5rem;">Drag the image to reposition &middot; use &plus; / &minus; to zoom</p>
                </div>
                <div class="modal-footer" style="padding:1rem 1.25rem 1.25rem;border-top:1px solid #f1f5f9;display:flex;justify-content:flex-end;gap:0.5rem;">
                    <button type="button" class="btn btn--secondary" data-crop-cancel>Cancel</button>
                    <button type="button" class="btn btn--primary" data-crop-apply>Apply &amp; Continue</button>
                </div>
            </div>`;
        document.body.appendChild(overlay);

        const imgEl = overlay.querySelector(".vh-crop-img");
        let cropper = null;
        let settled = false;

        const finish = (result) => {
            if (settled) return;
            settled = true;
            if (cropper) { try { cropper.destroy(); } catch (e) { /* noop */ } }
            overlay.remove();
            resolve(result);
        };

        const reader = new FileReader();
        reader.onload = (e) => {
            imgEl.src = e.target.result;
            imgEl.onload = () => {
                cropper = new Cropper(imgEl, {
                    aspectRatio: aspectRatio,
                    viewMode: 1,
                    dragMode: "move",
                    autoCropArea: 1,
                    background: false,
                    responsive: true,
                    movable: true,
                    zoomable: true,
                    guides: true,
                    checkOrientation: true,
                });
            };
        };
        reader.readAsDataURL(file);

        const q = (sel) => overlay.querySelector(sel);
        q("[data-crop-zoomin]").addEventListener("click", () => cropper && cropper.zoom(0.1));
        q("[data-crop-zoomout]").addEventListener("click", () => cropper && cropper.zoom(-0.1));
        q("[data-crop-rotate]").addEventListener("click", () => cropper && cropper.rotate(90));
        q("[data-crop-reset]").addEventListener("click", () => cropper && cropper.reset());
        overlay.querySelectorAll("[data-crop-cancel]").forEach((b) =>
            b.addEventListener("click", () => finish(null))
        );

        q("[data-crop-apply]").addEventListener("click", () => {
            if (!cropper) { finish(null); return; }
            const canvas = cropper.getCroppedCanvas({
                maxWidth: options.maxWidth || 1600,
                maxHeight: options.maxHeight || 1600,
                imageSmoothingQuality: "high",
                fillColor: "#ffffff",
            });
            if (!canvas) { finish(null); return; }
            canvas.toBlob((blob) => {
                if (!blob) { finish(null); return; }
                const baseName = (file.name || "image").replace(/\.[^.]+$/, "");
                const cropped = new File([blob], baseName + ".jpg", { type: "image/jpeg" });
                finish(cropped);
            }, "image/jpeg", 0.92);
        });
    });
};
