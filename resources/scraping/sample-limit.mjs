/**
 * Only a nonempty test sample may finish at a known collection limit.
 */
export function isCompletedSample(
    kind,
    rows,
    requestLimitReached,
    error,
    rowLimitError,
) {
    return (
        kind === 'test' &&
        rows > 0 &&
        (error === rowLimitError ||
            (requestLimitReached &&
                error instanceof Error &&
                error.message.includes('net::ERR_BLOCKED_BY_CLIENT')))
    );
}
