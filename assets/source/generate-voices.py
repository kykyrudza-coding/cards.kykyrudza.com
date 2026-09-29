"""Run with Python + edge-tts. Only the three public test phrases are sent to TTS."""
import asyncio
import json
from pathlib import Path
import edge_tts

ROOT = Path(__file__).resolve().parents[1]

async def main():
    phrases = json.loads((ROOT / 'audio/voices/uk/phrases.json').read_text(encoding='utf-8'))
    for phrase in phrases:
        output = ROOT / phrase['path']
        temporary = output.with_suffix('.tmp.mp3')
        try:
            await asyncio.wait_for(edge_tts.Communicate(phrase['text'], phrase['voice'], rate='-5%').save(str(temporary)), timeout=45)
            if temporary.stat().st_size < 1000:
                raise RuntimeError(f'Empty speech output: {phrase["id"]}')
            temporary.replace(output)
            print(f'{phrase["id"]}: {output.stat().st_size} bytes')
        finally:
            temporary.unlink(missing_ok=True)

asyncio.run(main())
