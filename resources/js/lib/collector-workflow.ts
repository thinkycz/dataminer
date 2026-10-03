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
    if (status === 'queued') return 'waiting';
    if (status === 'running') return 'running';
    if (status === 'failed') return 'failed';
    if (status === 'cancelled') return 'cancelled';
    return 'empty';
}

export function collectorNextAction(collector: {
    id: number;
    review_ready: boolean;
    active_version: number | null;
    last_run: { id: string } | null;
}): { label: string; href: string } {
    if (collector.review_ready)
        return {
            label: 'redesign.review',
            href: `/collectors/${collector.id}`,
        };
    if (collector.active_version === null)
        return {
            label: 'home.continue_setup',
            href: `/collectors/${collector.id}/setup`,
        };
    if (collector.last_run)
        return {
            label: 'home.view_results',
            href: `/runs/${collector.last_run.id}`,
        };
    return {
        label: 'home.open_collector',
        href: `/collectors/${collector.id}`,
    };
}

export function suggestedCollectorName(url: string): string {
    try {
        return new URL(url).hostname.replace(/^www\./, '');
    } catch {
        return '';
    }
}
