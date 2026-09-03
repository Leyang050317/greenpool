import test from 'node:test';
import assert from 'node:assert/strict';
import { vehicleSessionOptions } from './runtime-options.mjs';

test('native inference uses a bounded single-threaded allocation policy', () => {
    assert.equal(vehicleSessionOptions.intraOpNumThreads, 1);
    assert.equal(vehicleSessionOptions.interOpNumThreads, 1);
    assert.equal(vehicleSessionOptions.executionMode, 'sequential');
    assert.equal(vehicleSessionOptions.enableCpuMemArena, false);
    assert.equal(vehicleSessionOptions.enableMemPattern, false);
    assert.equal(vehicleSessionOptions.graphOptimizationLevel, 'basic');
});
