import { env, pipeline, RawImage } from '@huggingface/transformers';
import path from 'node:path';
import { vehicleSessionOptions } from './runtime-options.mjs';

const [imagePath, expectedView, model, cacheDirectory] = process.argv.slice(2);

if (!imagePath || !['FRONT', 'REAR', 'SIDE'].includes(expectedView)) {
    process.stderr.write('Usage: node vehicle-image-classifier.mjs <image> <FRONT|REAR|SIDE> <model> <cache>\n');
    process.exit(2);
}

env.cacheDir = path.resolve(cacheDirectory);
env.allowLocalModels = true;
env.allowRemoteModels = true;

const labels = [
    'a car photographed directly from the front showing its headlights and front grille',
    'a car photographed directly from behind showing its tail lights and rear plate',
    'a car photographed directly from the side showing its doors and wheels',
];
const viewLabels = {
    FRONT: labels[0],
    REAR: labels[1],
    SIDE: labels[2],
};

try {
    const classifier = await pipeline('zero-shot-image-classification', model, {
        dtype: 'q8',
        session_options: vehicleSessionOptions,
    });
    const image = await RawImage.read(path.resolve(imagePath));
    const vehiclePredictions = await classifier(image, [
        'a photograph containing a car',
        'a photograph without a car',
        'a document or screenshot',
    ]);
    const vehicleScores = Object.fromEntries(vehiclePredictions.map(({ label, score }) => [label, score]));
    const predictions = await classifier(image, labels);
    const scores = Object.fromEntries(predictions.map(({ label, score }) => [label, score]));
    const framingPredictions = await classifier(image, [
        'the entire car is fully visible from bumper to bumper',
        'only a small cropped part of a car is visible',
        'a person is the main subject standing beside a car',
        'the car is heavily blocked by a person or object',
    ]);
    const framingScores = Object.fromEntries(framingPredictions.map(({ label, score }) => [label, score]));
    // Analyse the centre/lower region where the vehicle sits, instead of allowing
    // grass, trees, sky, and walls to dominate the colour classification.
    const vehicleCrop = await image.crop([
        Math.round(image.width * 0.08),
        Math.round(image.height * 0.30),
        Math.round(image.width * 0.92),
        Math.round(image.height * 0.96),
    ]);
    const colourLabels = ['black', 'white', 'silver', 'grey', 'red', 'blue', 'green', 'yellow', 'brown or bronze'];
    const colourPredictions = await classifier(vehicleCrop, colourLabels.map(colour => `a ${colour} car body`));
    const rankedColours = colourPredictions
        .map(({ label, score }) => ({
            colour: label.replace(/^a | car body$/g, '').trim().toUpperCase().replace('BROWN OR BRONZE', 'BROWN'),
            score,
        }))
        .sort((a, b) => b.score - a.score);
    const detectedColour = rankedColours[0]?.colour ?? null;
    const colourGroup = ['GREY', 'SILVER', 'BROWN'].includes(detectedColour)
        ? 'NEUTRAL_METALLIC'
        : detectedColour;
    const colourConfidence = Number(rankedColours[0]?.score ?? 0);
    const colourMargin = colourConfidence - Number(rankedColours[1]?.score ?? 0);
    const viewScores = Object.fromEntries(
        Object.entries(viewLabels).map(([view, label]) => [view, Number(scores[label] ?? 0)]),
    );
    const rankedViews = Object.entries(viewScores).sort((a, b) => b[1] - a[1]);
    const [detectedView, bestViewScore] = rankedViews[0];
    const expectedScore = viewScores[expectedView];
    const margin = bestViewScore - rankedViews[1][1];
    const vehicleConfidence = Number(vehicleScores['a photograph containing a car'] ?? 0);
    const isVehicle = vehicleConfidence >= 0.22 && bestViewScore >= 0.45;
    const viewMatches = isVehicle && detectedView === expectedView;
    const personDominant = Number(framingScores['a person is the main subject standing beside a car'] ?? 0) >= 0.50;
    const requiresReview = !viewMatches || personDominant || expectedScore < 0.38 || margin < 0.035;
    const accepted = viewMatches && !requiresReview;

    let reasonCode = null;
    let message = `${expectedView.charAt(0)}${expectedView.slice(1).toLowerCase()} vehicle photo accepted.`;
    if (!isVehicle) {
        reasonCode = 'VEHICLE_NOT_CLEAR';
        message = 'Not accepted — vehicle not clearly recognized. Retake it with the whole car visible, no obstruction, good lighting, and less empty background.';
    } else if (personDominant) {
        reasonCode = 'PERSON_OR_OBSTRUCTION';
        message = 'Not accepted — a person or obstruction dominates the photo. Retake it with the entire car visible from end to end and nobody blocking it.';
    } else if (!viewMatches) {
        reasonCode = 'WRONG_VIEW';
        message = `Not accepted — wrong angle. This looks like a ${detectedView.toLowerCase()} view; upload a direct ${expectedView.toLowerCase()} view showing the whole car.`;
    } else if (requiresReview) {
        reasonCode = 'ANGLE_UNCLEAR';
        message = `Not accepted — the car is recognized, but the ${expectedView.toLowerCase()} angle is uncertain. Stand directly in front of that side and keep the whole car in frame.`;
    }

    process.stdout.write(`${JSON.stringify({
        is_vehicle: isVehicle,
        detected_view: isVehicle ? detectedView : 'NOT_A_VEHICLE',
        view_matches: viewMatches,
        quality: accepted ? 'ACCEPTABLE' : 'AMBIGUOUS',
        requires_review: requiresReview,
        accepted,
        reason_code: reasonCode,
        message,
        confidence: Number(expectedScore.toFixed(4)),
        vehicle_confidence: Number(vehicleConfidence.toFixed(4)),
        framing_confidence: Number((framingScores['a person is the main subject standing beside a car'] ?? 0).toFixed(4)),
        detected_colour: detectedColour,
        colour_group: colourGroup,
        colour_confidence: Number(colourConfidence.toFixed(4)),
        colour_reliable: colourConfidence >= 0.24 && colourMargin >= 0.055,
    })}\n`);
} catch (error) {
    process.stderr.write(`${error?.stack ?? error}\n`);
    process.exit(1);
}
