// Diagnostic only: synthetic pixels, no user photos or document data.
import { env, pipeline, RawImage } from '@huggingface/transformers';
import { vehicleSessionOptions } from './runtime-options.mjs';
import path from 'node:path';

env.cacheDir = path.resolve('tmp/vehicle-model-probe');
const report = (stage) => console.log(JSON.stringify({
    stage, rssMiB: Math.round(process.memoryUsage().rss / 1048576),
    peakMiB: Math.round(process.resourceUsage().maxRSS / 1024),
}));
report('before-model');
const classifier = await pipeline('zero-shot-image-classification', 'Xenova/clip-vit-base-patch32', {
    dtype: 'q8', session_options: vehicleSessionOptions,
});
report('after-model');
const image = new RawImage(new Uint8ClampedArray(800 * 450 * 3).fill(128), 800, 450, 3);
for (const count of [3, 4, 9]) {
    await classifier(image, Array.from({ length: count }, (_, i) => `a car with colour number ${i}`));
    report(`after-${count}-labels`);
}
await classifier.dispose();
report('disposed');
