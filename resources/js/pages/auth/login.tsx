import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('pages.auth.login', 'Log in')} />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex w-full min-w-0 flex-col gap-6 touch-manipulation"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-5">
                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t(
                                        'pages.auth.email',
                                        'Email address',
                                    )}
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    inputMode="email"
                                    enterKeyHint="next"
                                    placeholder="email@example.com"
                                    className="h-12 min-h-12 scroll-mb-28 text-base"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex flex-col gap-1 sm:flex-row sm:items-center">
                                    <Label htmlFor="password">
                                        {t(
                                            'pages.auth.password',
                                            'Password',
                                        )}
                                    </Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="text-sm sm:ml-auto"
                                            tabIndex={5}
                                        >
                                            {t(
                                                'pages.auth.forgot_password',
                                                'Forgot your password?',
                                            )}
                                        </TextLink>
                                    )}
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder={t(
                                        'pages.auth.password',
                                        'Password',
                                    )}
                                    className="h-12 min-h-12 scroll-mb-28 text-base"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex min-h-11 items-center gap-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                    className="size-5"
                                />
                                <Label htmlFor="remember" className="min-h-11 py-2">
                                    {t(
                                        'pages.auth.remember',
                                        'Remember me',
                                    )}
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 h-12 min-h-12 w-full scroll-mb-6 text-base"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                {t('pages.auth.login', 'Log in')}
                            </Button>
                        </div>

                        <div className="text-center text-sm text-balance text-muted-foreground">
                            {t(
                                'pages.auth.no_account',
                                "Don't have an account?",
                            )}{' '}
                            <TextLink href={register()} tabIndex={6}>
                                {t('pages.auth.sign_up', 'Sign up')}
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-wrap text-green-600">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Log in to your account',
    description: 'Enter your email and password below to log in',
};
