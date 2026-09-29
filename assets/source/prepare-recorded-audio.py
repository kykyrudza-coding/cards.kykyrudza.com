"""Prepare the checked-in Kenney CC0 recordings. Requires imageio-ffmpeg.
No synthesis: trim silence, filter rumble, peak normalize, encode OGG + MP3.
"""
import array
import json
from pathlib import Path
import subprocess
import imageio_ffmpeg

ROOT = Path(__file__).resolve().parents[1]
FFMPEG = imageio_ffmpeg.get_ffmpeg_exe()
MAPPING = {
    'cards/deal-01': 'card-slide-1.ogg',
    'cards/deal-02': 'card-slide-2.ogg',
    'cards/deal-03': 'card-slide-4.ogg',
    'cards/flip': 'card-place-1.ogg',
    'cards/collect': 'card-shove-1.ogg',
    'cards/shuffle': 'card-shuffle.ogg',
    'chips/single': 'chip-lay-1.ogg',
    'chips/bet': 'chips-stack-3.ogg',
    'chips/payout': 'chips-collide-2.ogg',
    'ui/click': 'chip-lay-2.ogg',
    'ui/win': 'chips-stack-1.ogg',
    'ui/lose': 'card-place-4.ogg',
}
report = []
for name, original in MAPPING.items():
    source = ROOT / 'source/audio-originals' / original
    pcm = subprocess.run([FFMPEG, '-v','error','-i',str(source),'-ac','1','-ar','44100','-af','highpass=f=70,lowpass=f=11000','-f','f32le','-'],capture_output=True,check=True).stdout
    values = array.array('f',pcm)
    active = [i for i,v in enumerate(values) if abs(v) > .006]
    if not active:
        raise RuntimeError(f'Silent source: {original}')
    values = values[max(0,active[0]-132):min(len(values),active[-1]+442)]
    if name == 'cards/shuffle':
        values = values[:35280]
    peak = max(abs(v) for v in values)
    gain = .71 / peak
    values = array.array('f',(v*gain*min(1,i/88,(len(values)-1-i)/220) for i,v in enumerate(values)))
    output = ROOT / 'audio' / name
    output.parent.mkdir(parents=True,exist_ok=True)
    sizes = {}
    for ext, codec in [('ogg',['-c:a','libvorbis','-q:a','5']),('mp3',['-c:a','libmp3lame','-b:a','128k'])]:
        path = output.with_suffix('.'+ext)
        subprocess.run([FFMPEG,'-v','error','-y','-f','f32le','-ar','44100','-ac','1','-i','-',*codec,str(path)],input=values.tobytes(),check=True)
        sizes[ext] = path.stat().st_size
    report.append({'id':name,'source':original,'author':'Kenney','license':'CC0-1.0','duration':round(len(values)/44100,3),'files':{ext:f'audio/{name}.{ext}' for ext in sizes},'bytes':sizes,'modified':'Trimmed, mono, filtered 70 Hz–11 kHz, normalized to -3 dBFS, short edge fades, encoded.'})
(ROOT / 'audio/recorded-manifest.json').write_text(json.dumps(report,indent=2)+'\n',encoding='utf-8')
print(json.dumps({'clips':len(report),'bytes':sum(sum(item['bytes'].values()) for item in report),'durations':{item['id']:item['duration'] for item in report}}))
