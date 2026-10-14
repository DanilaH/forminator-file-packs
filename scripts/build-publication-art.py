#!/usr/bin/env python3
"""Render original vector publication assets. Requires CairoSVG and Pillow."""
from pathlib import Path
import cairosvg
from PIL import Image
root = Path(__file__).resolve().parents[1]
out = root / 'docs/publication/assets'
out.mkdir(parents=True, exist_ok=True)
mark = '''<rect x="0" y="0" width="256" height="256" rx="52" fill="#153A61"/>
<path d="M44 95V79Q44 65 58 65H103L121 84H199Q212 84 212 98V184Q212 198 198 198H58Q44 198 44 184Z" fill="#6DE1C5"/>
<rect x="91" y="51" width="80" height="111" rx="10" transform="rotate(10 131 106)" fill="#AEDBCD"/>
<rect x="72" y="56" width="80" height="111" rx="10" fill="#FFFFFF"/>
<path d="M89 82H133M89 99H133M89 116H118" stroke="#153A61" stroke-width="7" stroke-linecap="round"/>
<path d="M38 118Q38 108 48 108H94L111 125H205Q218 125 216 139L208 188Q207 198 196 198H58Q48 198 46 187Z" fill="#4BC9B0"/>
<path d="M164 151V175M154 166L164 176L174 166" fill="none" stroke="#153A61" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>'''
icon = '<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 256 256">' + mark + '</svg>'
(out / 'icon.svg').write_text(icon)
banner = '''<svg xmlns="http://www.w3.org/2000/svg" width="1544" height="500" viewBox="0 0 1544 500">
<rect width="1544" height="500" fill="#EFF5FB"/>
<path d="M1080 0H1544V500H1200Z" fill="#DFEEE9"/>
<g font-family="DejaVu Sans, sans-serif" fill="#153A61">
<text x="72" y="110" font-size="66" font-weight="bold">File Packs</text>
<text x="74" y="160" font-size="32">for Forminator</text>
<text x="74" y="254" font-size="39" font-weight="bold">Submissions and attachments.</text>
<text x="74" y="309" font-size="39" font-weight="bold">Together.</text>
<text x="74" y="390" font-size="25" fill="#496579">Organized ZIP exports with HTML, CSV and JSON.</text>
</g>
<g transform="translate(1160 112) scale(1.08)">''' + mark + '</g></svg>'
(out / 'banner.svg').write_text(banner)
for source, name, width, height in [
 ('icon.svg', 'icon-128x128.png',128,128), ('icon.svg','icon-256x256.png',256,256),
 ('banner.svg','banner-772x250.png',772,250), ('banner.svg','banner-1544x500.png',1544,500)]:
 cairosvg.svg2png(url=str(out/source),write_to=str(out/name),output_width=width,output_height=height)
 with Image.open(out/name) as image: assert image.size==(width,height)
print('PASS original SVG sources and four exact-size PNG variants')
