# Asset attribution

## Recorded SFX

- Author: Kenney Vleugels (Kenney.nl).
- Pack: [Casino Audio 1.1](https://kenney.nl/assets/casino-audio).
- License: [CC0 1.0 Universal](https://creativecommons.org/publicdomain/zero/1.0/). Personal and commercial use permitted; attribution optional.
- Original license is preserved in `source/audio-originals/License.txt`.
- Selected original recordings are preserved in `source/audio-originals/`.
- Modified: silence trimmed; mono 44.1 kHz; 70 Hz high-pass / 11 kHz low-pass; peak normalization around −3 dBFS; short edge fades. Shuffle shortened to 0.8 s. No generated noise.
- Runtime formats: OGG Vorbis plus MP3 fallback. Only the supported format is decoded in a browser.

| Runtime clip | Original recording | Duration | OGG + MP3 bytes |
|---|---|---:|---:|
| `cards/deal-01` | `card-slide-1.ogg` | 0.187 s | 10,194 |
| `cards/deal-02` | `card-slide-2.ogg` | 0.584 s | 20,087 |
| `cards/deal-03` | `card-slide-4.ogg` | 0.45 s | 16,545 |
| `cards/flip` | `card-place-1.ogg` | 0.247 s | 11,673 |
| `cards/collect` | `card-shove-1.ogg` | 0.766 s | 24,766 |
| `cards/shuffle` | `card-shuffle.ogg` | 0.8 s | 25,444 |
| `chips/single` | `chip-lay-1.ogg` | 0.122 s | 8,253 |
| `chips/bet` | `chips-stack-3.ogg` | 0.355 s | 14,162 |
| `chips/payout` | `chips-collide-2.ogg` | 0.128 s | 8,429 |
| `ui/click` | `chip-lay-2.ogg` | 0.098 s | 7,684 |
| `ui/win` | `chips-stack-1.ogg` | 0.119 s | 8,351 |
| `ui/lose` | `card-place-4.ogg` | 0.601 s | 20,372 |

Total: 175,960 bytes for both codec sets (12 clips, 24 files).

Win/lose use subtle recorded chip/card cues, not musical jingles. The button click is a quieter chip recording.

## Graphics

52 faces, back, table and three chips are original procedural SVG art in `source/generate-classic.mjs`. No external texture pack. Georgia/serif is referenced by name; no Georgia font file is distributed.

## Test dealer voice

Existing three Ukrainian MP3 files use Microsoft `uk-UA-OstapNeural` via [edge-tts](https://github.com/rany2/edge-tts). These are synthetic test recordings, not Kenney assets and not covered by CC0. Commercial redistribution rights for the service voice have not been independently verified. The generation script and text are preserved; replace these prototype clips with a licensed actor/service output before commercial distribution.

## 3D

No external 3D model is included. The researched [Kenney Playing Cards Pack](https://kenney.nl/assets/playing-cards-pack) is a 2D pack, not a verified 3D replacement. No model was selected with verified geometry, textures and license. Full 3D remains a separate stage; use one card mesh with interchangeable face/back textures.

## Midnight and additional recorded cues

Midnight SVG graphics are an original vector variant created for this project.
Five additional cues (turn, split, double, push, blackjack) layer the included Kenney CC0 recordings with timing, gain, filtering and fades. No new third-party audio source or synthesized noise is used. Exact sources and durations are in `audio/recorded-manifest.json`; reproduction is in `source/extend-audio.py`.
