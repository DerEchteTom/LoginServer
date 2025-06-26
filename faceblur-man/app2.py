# app2.py – Manual Blur ohne Zoom-Modifikationen
import tempfile
import numpy as np
from PIL import Image, ImageFilter
import gradio as gr

def blur_with_editor(editor_data, radius):
    bg   = editor_data["background"]
    mask = editor_data["layers"][0].convert("L")
    blurred = bg.filter(ImageFilter.GaussianBlur(radius))

    mask_np = np.array(mask.resize(bg.size)) > 10
    img_np  = np.array(bg.convert("RGB"))
    blur_np = np.array(blurred.convert("RGB"))

    out_np = np.where(mask_np[:, :, None], blur_np, img_np)
    return Image.fromarray(out_np)

def prepare_download(img):
    if img:
        tmp = tempfile.NamedTemporaryFile(delete=False, suffix=".png")
        img.save(tmp.name)
        return tmp.name

with gr.Blocks() as demo:
    gr.Markdown("## 🎨 Manual-Only Face Blur")

    with gr.Row():
        with gr.Column():
            editor   = gr.ImageEditor(type="pil", label="Upload & Paint Blur Mask")
            radius   = gr.Slider(1, 50, value=10, step=1, label="Blur Radius")
            btn_blur = gr.Button("Apply Blur")
        with gr.Column():
            result   = gr.Image(type="pil", label="Result")
            download = gr.File(label="Download", interactive=False)

    btn_blur.click(
        fn=blur_with_editor,
        inputs=[editor, radius],
        outputs=result
    ).then(
        fn=prepare_download,
        inputs=result,
        outputs=download
    )

    demo.launch(server_name="0.0.0.0", server_port=7861)


