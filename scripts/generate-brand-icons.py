"""Rasterize resources/brand/logo.png into Windows, PWA, and in-app logo sizes."""

from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "resources" / "brand" / "logo.png"
RESAMPLE = Image.Resampling.LANCZOS


def crop_mark(image: Image.Image) -> Image.Image:
    """Square crop of the gold badge, above the KOSPAL wordmark."""
    rgb = image.convert("RGB")
    width, height = rgb.size
    pixels = rgb.load()

    def row_active(y: int, threshold: int = 28) -> bool:
        for x in range(0, width, 2):
            red, green, blue = pixels[x, y]
            if red > threshold or green > threshold or blue > threshold:
                return True
        return False

    rows = [y for y in range(height) if row_active(y)]
    split = rows[-1]
    previous = rows[0]
    for y in rows[1:]:
        if y > previous + 8:
            split = previous
            break
        previous = y

    min_x, min_y, max_x, max_y = width, height, 0, 0
    for y in range(split + 1):
        for x in range(width):
            red, green, blue = pixels[x, y]
            if red > 28 or green > 28 or blue > 28:
                min_x = min(min_x, x)
                min_y = min(min_y, y)
                max_x = max(max_x, x)
                max_y = max(max_y, y)

    center_x = (min_x + max_x) / 2
    center_y = (min_y + max_y) / 2
    side = max(max_x - min_x, max_y - min_y) + 24
    top = int(round(center_y - side / 2))
    left = int(round(center_x - side / 2))
    word_top = split + 2
    if top + side > word_top:
        side = word_top - top
    left = max(0, min(left, width - side))
    top = max(0, min(top, height - side))

    return rgb.crop((left, top, left + side, top + side))


def square(image: Image.Image, size: int) -> Image.Image:
    return image.convert("RGB").resize((size, size), RESAMPLE)


def maskable(image: Image.Image, size: int) -> Image.Image:
    canvas = Image.new("RGB", (size, size), (0, 0, 0))
    inner = int(round(size * 0.76))
    logo = square(image, inner)
    offset = (size - inner) // 2
    canvas.paste(logo, (offset, offset))
    return canvas


def save_png(image: Image.Image, path: Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    image.save(path, format="PNG", optimize=True)


def save_ico(image: Image.Image, path: Path, sizes: list[int]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    image.save(
        path,
        format="ICO",
        sizes=[(size, size) for size in sizes],
    )


def main() -> None:
    logo = Image.open(SOURCE).convert("RGB")
    if logo.size != (1024, 1024):
        logo = square(logo, 1024)

    mark = crop_mark(logo)
    public = ROOT / "public"
    tauri = ROOT / "src-tauri" / "icons"

    save_png(logo, public / "brand" / "logo.png")
    save_png(square(mark, 512), public / "brand" / "mark.png")
    save_png(square(logo, 32), public / "favicon.png")
    save_ico(logo, public / "favicon.ico", [16, 24, 32, 48, 64])
    save_png(square(logo, 180), public / "apple-touch-icon.png")
    save_png(square(logo, 192), public / "pwa" / "icon-192.png")
    save_png(square(logo, 512), public / "pwa" / "icon-512.png")
    save_png(maskable(logo, 192), public / "pwa" / "icon-maskable-192.png")
    save_png(maskable(logo, 512), public / "pwa" / "icon-maskable-512.png")

    save_png(square(logo, 32), tauri / "32x32.png")
    save_png(square(logo, 128), tauri / "128x128.png")
    save_png(square(logo, 256), tauri / "128x128@2x.png")
    save_png(square(logo, 512), tauri / "icon.png")
    save_ico(logo, tauri / "icon.ico", [16, 24, 32, 48, 64, 128, 256])

    print(f"Mark crop size: {mark.size[0]}x{mark.size[1]}")
    print("Wrote brand, favicon, PWA, and Tauri icons.")


if __name__ == "__main__":
    main()
