export interface CollectorCard {
    id: number;
    name: string;
    start_url: string;
    status: string;
    active_version: number | null;
    source_type: string | null;
    review_ready: boolean;
    last_run: {
        id: string;
        status: string;
        rows: number;
        finished_at: string | null;
    } | null;
    schedule: { status: string; next_run_at: string | null } | null;
}
export interface CollectorPreview {
    version_id: number;
    version: number;
    matches_draft: boolean;
    can_activate: boolean;
    is_active: boolean;
}
