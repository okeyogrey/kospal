import { Form, Head, Link, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { accept, register } from '@/routes/invitations';
import { login, logout } from '@/routes';

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
    account_exists,
    email_matches,
}: {
    invitation: Invitation;
    account_exists: boolean;
    email_matches: boolean;
}) {
    const { auth } = usePage().props;
    const signedIn = Boolean(auth.user);

    return (
        <>
            <Head title="Accept invitation" />
            <div className="kospal-shell-bg flex min-h-svh items-center justify-center p-6 pt-[max(1.5rem,env(safe-area-inset-top))]">
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
                    ) : signedIn && !email_matches ? (
                        <div className="space-y-3">
                            <p className="text-sm text-destructive">
                                You are signed in as{' '}
                                <strong>{auth.user?.email}</strong>, but this
                                invitation is for{' '}
                                <strong>{invitation.email}</strong>. Sign out,
                                then continue with the invited email.
                            </p>
                            <Form {...logout.form()}>
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        Sign out
                                    </Button>
                                )}
                            </Form>
                        </div>
                    ) : signedIn && email_matches ? (
                        <Form {...accept.form(invitation.token)}>
                            {({ processing, errors }) => (
                                <div className="space-y-3">
                                    <InputError message={errors.invitation} />
                                    <Button type="submit" disabled={processing}>
                                        Accept invitation
                                    </Button>
                                </div>
                            )}
                        </Form>
                    ) : account_exists ? (
                        <div className="space-y-3">
                            <p className="text-sm text-muted-foreground">
                                An account already exists for this email. Sign
                                in to accept the invitation.
                            </p>
                            <Button asChild>
                                <Link href={login()}>Sign in to accept</Link>
                            </Button>
                        </div>
                    ) : (
                        <Form
                            {...register.form(invitation.token)}
                            className="space-y-4"
                            resetOnSuccess={[
                                'password',
                                'password_confirmation',
                            ]}
                        >
                            {({ processing, errors }) => (
                                <>
                                    <InputError message={errors.invitation} />
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Your name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            autoFocus
                                            autoComplete="name"
                                            placeholder="Full name"
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="password">
                                            Create a password
                                        </Label>
                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            required
                                            autoComplete="new-password"
                                            placeholder="Password"
                                        />
                                        <InputError message={errors.password} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="password_confirmation">
                                            Confirm password
                                        </Label>
                                        <PasswordInput
                                            id="password_confirmation"
                                            name="password_confirmation"
                                            required
                                            autoComplete="new-password"
                                            placeholder="Confirm password"
                                        />
                                        <InputError
                                            message={
                                                errors.password_confirmation
                                            }
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        className="w-full"
                                        disabled={processing}
                                    >
                                        Create account & join
                                    </Button>
                                    <p className="text-xs text-muted-foreground">
                                        Already have a KOSPAL account?{' '}
                                        <Link
                                            href={login()}
                                            className="underline underline-offset-4"
                                        >
                                            Sign in
                                        </Link>
                                    </p>
                                </>
                            )}
                        </Form>
                    )}
                </div>
            </div>
        </>
    );
}
