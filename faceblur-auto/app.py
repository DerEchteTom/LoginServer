# -*- coding: utf-8 -*-
import os
import tempfile
import logging.config
import face_recognition
import numpy as np
import cv2
from PIL import Image, ImageDraw, ImageFont
import gradio as gr

# ——— Logging Setup ——————————————————————————
if os.path.exists("logging.conf"):
    logging.config.fileConfig("logging.conf", disable_existing_loggers=False)
else:
    logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)
logger.info("Starting Auto Face Blur v1.3.1")

# ——— Constants ——————————————————————————————————
BOX_COLORS = ["red","green","blue","orange","purple","cyan","magenta","yellow"]
FONT_PATH  = os.path.join(os.path.dirname(__file__),"fonts","DejaVuSans-Bold.ttf")

# ——— Utility: To PIL Image ——————————————————————
def to_pil_image(data):
    if isinstance(data, dict) and "composite" in data:
        return data["composite"]
    if isinstance(data, Image.Image):
        return data
    if isinstance(data, np.ndarray):
        return Image.fromarray(data)
    raise TypeError(f"Unsupported image type: {type(data)}")

# ——— Face Detection + Preview —————————————————————
def detect_faces(image):
    image = to_pil_image(image)
    logger.info("Face detection started (auto).")
    np_img    = np.array(image.convert("RGB"))
    locations = face_recognition.face_locations(np_img, number_of_times_to_upsample=2)
    labels    = [f"Face {i+1}" for i in range(len(locations))]
    logger.info(f"{len(labels)} face(s) detected.")

    # Draw colored rectangles + indices
    preview = image.copy()
    draw    = ImageDraw.Draw(preview)
    font_sz = max(16, int(image.height * 0.045))
    try:
        font = ImageFont.truetype(FONT_PATH, size=font_sz)
    except Exception as e:
        font = ImageFont.load_default()
        logger.warning(f"Font fallback: {e}")

    for idx, (top, right, bottom, left) in enumerate(locations):
        color = BOX_COLORS[idx % len(BOX_COLORS)]
        draw.rectangle([left, top, right, bottom], outline=color, width=4)
        draw.text((left+5, top+5), str(idx+1),
                  fill="white", font=font,
                  stroke_width=2, stroke_fill="black")

    # Update CheckboxGroup + state + preview image
    return gr.update(choices=labels, value=[]), locations, preview

# ——— Blur-Funktion ———————————————————————————
def blur_faces(image, selected_faces, face_locations, blur_strength):
    image = to_pil_image(image)
    logger.info(f"Blurring started. Selected: {selected_faces}")
    img_np = np.array(image.convert("RGB"))

    for idx, (top, right, bottom, left) in enumerate(face_locations):
        label = f"Face {idx+1}"
        if label in selected_faces:
            region = img_np[top:bottom, left:right]
            k      = max(3, blur_strength | 1)
            img_np[top:bottom, left:right] = cv2.GaussianBlur(region, (k, k), 0)

    return Image.fromarray(img_np)

# ——— Download Prep ———————————————————————————
def prepare_download(pil_image):
    if pil_image:
        tmp = tempfile.NamedTemporaryFile(delete=False, suffix=".png")
        pil_image.save(tmp.name)
        return tmp.name
    return None

# ——— Gradio UI ———————————————————————————————————
with gr.Blocks(css="#download_field input[type='file']{display:none;}") as demo:
    gr.Markdown("## 👁️‍🗨️ Auto Face Blur (v1.3.1 – Auto-Detect)")

    with gr.Row():
        with gr.Column(scale=1):
            # 1) Upload + Auto-Detect
            img_input      = gr.Image(type="pil", label="Upload Image")
            face_selector  = gr.CheckboxGroup(label="Detected Faces", choices=[])
            blur_slider    = gr.Slider(15, 101, value=45, step=2, label="Blur Strength")
            btn_blur       = gr.Button("Apply Blur")
        with gr.Column(scale=1):
            # 2) Vorschau + Ausgabe + Download
            img_marked     = gr.Image(type="pil", label="Detected Faces Preview")
            img_output     = gr.Image(type="pil", label="Blurred Output")
            download_file  = gr.File(label="Download Result",
                                     interactive=False,
                                     elem_id="download_field")

    # State zum Speichern der Face-Koordinaten
    state_locations = gr.State([])

    # Auto-Detect nach Bild-Upload oder Änderung
    img_input.change(
        fn=detect_faces,
        inputs=img_input,
        outputs=[face_selector, state_locations, img_marked]
    )

    # Blur anwenden
    btn_blur.click(
        fn=blur_faces,
        inputs=[img_input, face_selector, state_locations, blur_slider],
        outputs=img_output
    ).then(
        fn=prepare_download,
        inputs=img_output,
        outputs=download_file
    )

    demo.launch(server_name="0.0.0.0")
