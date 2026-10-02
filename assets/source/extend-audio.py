"""Layer existing CC0 recordings into five distinct game cues; no synthesis."""
import array, json, subprocess
from pathlib import Path
import imageio_ffmpeg
root = Path(__file__).resolve().parents[1]
ffmpeg = imageio_ffmpeg.get_ffmpeg_exe()
cues = {
    'turn': [('chip-lay-2.ogg', 0, .55), ('chip-lay-1.ogg', .16, .7)],
    'split': [('card-slide-1.ogg', 0, .8), ('card-slide-4.ogg', .22, .8)],
    'double': [('chip-lay-1.ogg', 0, .7), ('chips-stack-3.ogg', .14, .9)],
    'push': [('card-place-1.ogg', 0, .65), ('chip-lay-2.ogg', .23, .5)],
    'blackjack': [('chips-stack-1.ogg', 0, .8), ('chips-stack-3.ogg', .17, .85), ('chips-collide-2.ogg', .48, .75)],
}
report = json.loads((root/'audio/recorded-manifest.json').read_text())
report = [clip for clip in report if clip['id'] not in ['ui/'+name for name in cues]]
for name, layers in cues.items():
    mix = []
    for source, delay, gain in layers:
        raw = subprocess.run([ffmpeg,'-v','error','-i',str(root/'source/audio-originals'/source),'-ac','1','-ar','44100','-af','highpass=f=70,lowpass=f=11000','-f','f32le','-'], capture_output=True,check=True).stdout
        samples = array.array('f',raw)
        active = [i for i,v in enumerate(samples) if abs(v)>.006]
        samples = samples[max(0,active[0]-132):min(len(samples),active[-1]+442)]
        offset = round(delay*44100)
        mix.extend([0.] * max(0, offset+len(samples)-len(mix)))
        peak = max(abs(v) for v in samples)
        for i,v in enumerate(samples): mix[offset+i] += v/peak*gain*min(1,i/88,(len(samples)-1-i)/220)
    peak = max(abs(v) for v in mix)
    pcm = array.array('f',(v*.65/peak for v in mix)).tobytes()
    files, sizes = {}, {}
    for ext,codec in [('ogg',['-c:a','libvorbis','-q:a','5']),('mp3',['-c:a','libmp3lame','-b:a','128k'])]:
        relative = f'audio/ui/{name}.{ext}'
        path = root/relative
        subprocess.run([ffmpeg,'-v','error','-y','-f','f32le','-ar','44100','-ac','1','-i','-',*codec,str(path)],input=pcm,check=True)
        files[ext],sizes[ext] = relative,path.stat().st_size
    report.append(dict(id='ui/'+name,source=[layer[0] for layer in layers],author='Kenney',license='CC0-1.0',duration=round(len(mix)/44100,3),files=files,bytes=sizes,modified='Layered recorded cues, trimmed, filtered, normalized, edge fades.'))
(root/'audio/recorded-manifest.json').write_text(json.dumps(report,indent=2)+'\n',encoding='utf-8')
print(f'{len(report)} SFX ready')
