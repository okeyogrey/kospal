import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    type EditionCard,
    changeTypeLabel,
    featureLabel,
    selectEditionLabel,
} from '@/lib/editions';
import { cn } from '@/lib/utils';

export function EditionCards({
    plans,
    selected,
    onSelect,
    disabled = false,
}: {
    plans: EditionCard[];
    selected: string;
    onSelect: (key: string) => void;
    disabled?: boolean;
}) {
    return (
        <section className="grid gap-4 lg:grid-cols-3">
            {plans.map((plan) => {
                const isSelected = plan.key === selected;

                return (
                    <div
                        key={plan.key}
                        className={cn(
                            'rounded-2xl border p-5',
                            isSelected
                                ? 'border-primary bg-primary/5'
                                : 'border-border/80 bg-card/80',
                        )}
                    >
                        <div className="mb-2 flex flex-wrap items-center gap-2">
                            <h2 className="font-display text-xl font-semibold">
                                {plan.name}
                            </h2>
                            {plan.is_current ? <Badge>Current</Badge> : null}
                            {plan.change_type && !plan.is_current ? (
                                <Badge variant="secondary">
                                    {changeTypeLabel(plan.change_type)}
                                </Badge>
                            ) : null}
                            {plan.change_type === 'renew' && plan.is_current ? (
                                <Badge variant="outline">Renew</Badge>
                            ) : null}
                        </div>
                        <p className="mb-4 text-sm text-muted-foreground">
                            {plan.description}
                        </p>
                        {plan.price_formatted ? (
                            <p className="mb-3 text-sm">
                                {plan.discount_percent ? (
                                    <>
                                        <span className="text-muted-foreground line-through">
                                            {plan.price_formatted}
                                        </span>{' '}
                                        <span className="font-semibold">
                                            {plan.due_formatted}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {' '}
                                            / month · {plan.discount_percent}%
                                            off
                                        </span>
                                    </>
                                ) : (
                                    <span className="font-semibold">
                                        {plan.price_formatted}
                                        <span className="font-normal text-muted-foreground">
                                            {' '}
                                            / month
                                        </span>
                                    </span>
                                )}
                            </p>
                        ) : null}
                        <p className="mb-3 text-sm font-medium">
                            {plan.max_branches} branches ·{' '}
                            {plan.max_staff ?? 'Unlimited'} staff
                        </p>
                        <ul className="space-y-1.5 text-sm">
                            {plan.features.map((feature) => (
                                <li key={feature}>{featureLabel(feature)}</li>
                            ))}
                        </ul>
                        <Button
                            type="button"
                            variant={isSelected ? 'default' : 'outline'}
                            className="mt-4 w-full"
                            disabled={disabled}
                            onClick={() => onSelect(plan.key)}
                        >
                            {isSelected
                                ? `${plan.name} selected`
                                : selectEditionLabel(plan)}
                        </Button>
                    </div>
                );
            })}
        </section>
    );
}
