// Limit native ONNX allocations; Node's heap limit does not cover these buffers.
export const vehicleSessionOptions = {
    intraOpNumThreads: 1,
    interOpNumThreads: 1,
    executionMode: 'sequential',
    enableCpuMemArena: false,
    enableMemPattern: false,
    graphOptimizationLevel: 'basic',
};
