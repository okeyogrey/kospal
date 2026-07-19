import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { accept } from '@/routes/invitations';
import { login } from '@/routes';

type Invitation = {
    token: string;
    email: string;
    role: string;
    business_name: string | null;
    expires_at: string;
    is_acceptable: boolean;
};

export default function AcceptInvitation({
    invitation,
}: {
    invitation: Invitation;
}) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Accept invitation" />
            <div className="kospal-shell-bg flex min-h-svh items-center justify-center p-6">
                <div className="w-full max-w-md space-y-4 rounded-2xl border border-border/80 bg-card/90 p-6 shadow-sm">
                    <h1 className="font-display text-2xl font-semibold">
                        Staff invitation
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Join {invitation.business_name ?? 'a business'} as{' '}
                        <strong>{invitation.role}</strong> using{' '}
                        <strong>{invitation.email}</strong>.
                    </p>

                    {!invitation.is_acceptable ? (
                        <p className="text-sm text-destructive">
                            This invitation is no longer valid.
                        </p>
                    ) : !auth.user ? (
                        <Button asChild>
                            <Link href={login()}>Log in to accept</Link>
                        </Button>
                    ) : (
                        <Form {...accept.form(invitation.token)}>
                            {({ processing }) => (
                                <Button type="submit" disabled={processing}>
                                    Accept invitation
                                </Button>
                            )}
                        </Form>
                    )}
                </div>
            </div>
        </>
    );
}
