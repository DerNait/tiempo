export interface Category {
    id: number;
    name: string;
    slug: string;
    group_name: string | null;
    icon: string;
    color: string;
    sort_order: number;
    is_active: boolean;
    is_favorite: boolean;
}

export interface TimeEntry {
    id: number;
    category_id: number;
    category?: Category;
    description: string | null;
    started_at: string;
    ended_at: string | null;
    is_open: boolean;
    duration_seconds: number;
    source: string;
}

export interface PeriodTotals {
    tracked_seconds: number;
    elapsed_seconds: number;
    coverage: number;
}

export interface AuditState {
    started_at: string;
    ends_at: string;
    total_days: number;
    day_number: number;
    finished: boolean;
}

export interface Status {
    server_time: string;
    server_time_unix: number;
    timezone: string;
    current_entry: TimeEntry | null;
    today: PeriodTotals & { date: string };
    week: PeriodTotals & { week_start: string };
    audit: AuditState | null;
    favorites: Category[];
}

export interface CategoryTotal {
    category_id: number;
    name: string;
    group_name: string | null;
    color: string;
    icon: string;
    seconds: number;
    minutes: number;
    share: number;
}

export interface Gap {
    start: string;
    end: string;
    seconds: number;
}

export interface DayReport {
    date: string;
    tracked_seconds: number;
    elapsed_seconds: number;
    coverage: number;
    by_category: CategoryTotal[];
    timeline: TimeEntry[];
    gaps: Gap[];
}

export type BudgetType = 'minimum' | 'maximum' | 'reference';
export type BudgetStatus = 'on_track' | 'pending' | 'exceeded' | 'reference';

export interface BudgetRow {
    category_id: number;
    category: string;
    group_name: string | null;
    color: string;
    icon: string;
    budget_type: BudgetType;
    target_minutes: number;
    actual_minutes: number;
    difference_minutes: number;
    percent: number | null;
    status: BudgetStatus;
}

export interface BudgetReport {
    week_start: string;
    rows: BudgetRow[];
    most_neglected: BudgetRow | null;
    biggest_overrun: BudgetRow | null;
}

export interface WeekReport {
    week_start: string;
    week_end: string;
    tracked_seconds: number;
    elapsed_seconds: number;
    coverage: number;
    by_category: CategoryTotal[];
    daily: { date: string; seconds: number; elapsed_seconds: number }[];
    budget: BudgetReport;
    previous_week: {
        week_start: string;
        tracked_seconds: number;
        by_category: CategoryTotal[];
    };
}

export interface Settings {
    id: number;
    name: string;
    email: string;
    timezone: string;
    week_starts_on: number;
    accent_color: string;
    audit_mode_enabled: boolean;
    audit_started_at: string | null;
    audit_days: number;
    onboarded: boolean;
    rainmeter_priority_category_id: number | null;
    rainmeter_leak_category_id: number | null;
}

export interface WeeklyReview {
    id?: number;
    week_start: string;
    biggest_time_leak: string | null;
    most_neglected_priority: string | null;
    what_worked: string | null;
    what_did_not_work: string | null;
    next_week_adjustment: string | null;
    notes: string | null;
}

export interface ApiToken {
    id: number;
    name: string;
    abilities: string[];
    last_used_at: string | null;
    created_at: string;
}
