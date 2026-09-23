import type { PaymentInstructions } from '@/lib/editions';

export function PaymentInstructionsCard({
    instructions,
}: {
    instructions: PaymentInstructions | null;
}) {
    if (!instructions) {
        return null;
    }

    return (
        <section className="rounded-2xl border border-border/80 bg-card/80 p-5">
            <h2 className="mb-2 font-medium">{instructions.title}</h2>
            <p className="mb-4 whitespace-pre-wrap text-sm text-muted-foreground">
                {instructions.body}
            </p>
            <dl className="grid gap-3 text-sm sm:grid-cols-2">
                {instructions.bank_name ? (
                    <div>
                        <dt className="text-muted-foreground">Bank</dt>
                        <dd className="font-medium">{instructions.bank_name}</dd>
                    </div>
                ) : null}
                {instructions.account_name ? (
                    <div>
                        <dt className="text-muted-foreground">Account name</dt>
                        <dd className="font-medium">
                            {instructions.account_name}
                        </dd>
                    </div>
                ) : null}
                {instructions.account_number ? (
                    <div>
                        <dt className="text-muted-foreground">
                            Account number
                        </dt>
                        <dd className="font-medium">
                            {instructions.account_number}
                        </dd>
                    </div>
                ) : null}
                {instructions.mobile_money ? (
                    <div>
                        <dt className="text-muted-foreground">Mobile money</dt>
                        <dd className="font-medium">
                            {instructions.mobile_money}
                        </dd>
                    </div>
                ) : null}
            </dl>
            {instructions.support_note ? (
                <p className="mt-4 text-sm text-muted-foreground">
                    {instructions.support_note}
                </p>
            ) : null}
        </section>
    );
}
