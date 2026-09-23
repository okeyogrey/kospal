export const FEATURE_LABELS: Record<string, string> = {
    core: 'Core POS, catalog, and inventory',
    advanced_reports: 'Advanced reports',
    csv_export: 'CSV export',
    csv_import: 'CSV import',
    excel_import: 'Excel import',
    excel_export: 'Excel export',
    pdf_reports: 'PDF reports',
    stock_transfers: 'Stock transfers',
    purchase_orders: 'Purchase orders',
    stock_counts: 'Stock counts',
    customer_credit: 'Customer credit',
    audit_logs: 'Enterprise audit logs',
    consolidated_reports: 'Consolidated multi-branch reports',
};

export type EditionChangeType = 'upgrade' | 'renew' | 'downgrade';

export type EditionCard = {
    key: string;
    name: string;
    description: string;
    max_branches: number;
    max_staff: number | null;
    features: string[];
    is_current: boolean;
    change_type: EditionChangeType | null;
    currency?: string;
    price_minor?: number;
    price_formatted?: string;
    due_minor?: number;
    due_formatted?: string;
    discount_percent?: number;
};

export type PaymentInstructions = {
    title: string;
    body: string;
    bank_name: string | null;
    account_name: string | null;
    account_number: string | null;
    mobile_money: string | null;
    support_note: string | null;
};

export type EditionRequestRecord = {
    id: number;
    requested_plan: string;
    current_plan: string;
    change_type: EditionChangeType;
    status: string;
    notes: string | null;
    transaction_code: string | null;
    reviewer_notes: string | null;
    reviewed_by: string | null;
    created_at: string | null;
    reviewed_at: string | null;
};

export function featureLabel(feature: string): string {
    return FEATURE_LABELS[feature] ?? feature;
}

export function changeTypeLabel(type: EditionChangeType): string {
    switch (type) {
        case 'upgrade':
            return 'Upgrade';
        case 'renew':
            return 'Renew';
        case 'downgrade':
            return 'Downgrade';
    }
}

export function selectEditionLabel(plan: EditionCard): string {
    if (plan.change_type === 'upgrade') {
        return `Upgrade to ${plan.name}`;
    }

    if (plan.change_type === 'downgrade') {
        return `Downgrade to ${plan.name}`;
    }

    if (plan.change_type === 'renew') {
        return `Renew ${plan.name}`;
    }

    return `Select ${plan.name}`;
}

export function suggestedEdition(currentPlan: string): string {
    return currentPlan === 'starter' ? 'pro' : currentPlan;
}

export function editionRequestCopy(input: {
    businessName: string;
    machineId?: string | null;
    currentPlan: string;
    currentStatus: string;
    requestedPlan: string;
    changeType: EditionChangeType;
}): string {
    const lines = [
        'KOSPAL edition request',
        `Business: ${input.businessName}`,
        input.machineId ? `Machine ID: ${input.machineId}` : null,
        `Current edition: ${input.currentPlan} (${input.currentStatus})`,
        `Requested edition: ${input.requestedPlan} (${changeTypeLabel(input.changeType).toLowerCase()})`,
        'This does not unlock features. Activate a matching license key or wait for approval after payment is confirmed.',
    ];

    return lines.filter((line): line is string => line !== null).join('\n');
}
