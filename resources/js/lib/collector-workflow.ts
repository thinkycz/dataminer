export interface WorkflowVersion {
    id: number;
    status: string;
    sample_run_id: string | null;
}
export type CollectorStage =
    | 'draft'
    | 'generating'
    | 'approval'
    | 'testing'
    | 'sample'
    | 'ready'
    | 'failed'
    | 'rejected';
export function collectorStage(
    status: string,
    pendingApproval: boolean,
    versions: WorkflowVersion[],
    activeVersionId: number | null,
): CollectorStage {
    if (pendingApproval) return 'approval';
    if (status === 'generating' || status === 'testing') return status;
    const latest = versions[0];
    if (status === 'failed') return 'failed';
    if (status === 'ready' && activeVersionId !== null) return 'ready';
    if (latest?.status === 'tested' && latest.id !== activeVersionId)
        return 'sample';
    if (activeVersionId !== null) return 'ready';
    if (latest?.status === 'rejected') return 'rejected';
    if (latest?.status === 'draft') return 'failed';
    if (status === 'pending_approval') return 'rejected';
    return 'draft';
}
export function resultEmptyState(status: string, filtered: boolean): string {
    if (filtered) return 'filtered';
    if (status === 'queued' || status === 'running') return 'waiting';
    if (status === 'failed') return 'failed';
    if (status === 'cancelled') return 'cancelled';
    return 'empty';
}
