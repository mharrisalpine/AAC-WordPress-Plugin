import React, { useMemo, useState } from 'react';
import { motion } from 'framer-motion';
import { ArrowLeft, KeyRound } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAuth } from '@/hooks/useAuth';

const getPasswordStatus = (password) => {
  if (!password) {
    return { label: 'Enter at least 8 characters.', tone: 'text-stone-500' };
  }

  if (password.length < 8) {
    return { label: 'Too short', tone: 'text-[#b71c1c]' };
  }

  if (password.length < 12) {
    return { label: 'Good', tone: 'text-[#9a7b20]' };
  }

  return { label: 'Strong', tone: 'text-emerald-700' };
};

const ChangePasswordPage = () => {
  const navigate = useNavigate();
  const { changePassword, loading } = useAuth();
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const passwordStatus = useMemo(() => getPasswordStatus(newPassword), [newPassword]);
  const passwordsMatch = confirmPassword !== '' && newPassword === confirmPassword;

  const handleSubmit = async (event) => {
    event.preventDefault();
    setSubmitting(true);

    try {
      const { error } = await changePassword(currentPassword, newPassword, confirmPassword);
      if (!error) {
        setCurrentPassword('');
        setNewPassword('');
        setConfirmPassword('');
        navigate('/account', { replace: true });
      }
    } finally {
      setSubmitting(false);
    }
  };

  const busy = loading || submitting;

  return (
    <div className="py-6">
      <motion.div
        initial={{ opacity: 0, y: 18 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.45 }}
        className="space-y-6"
      >
        <div className="rounded-none border-b-2 border-[#b71c1c] bg-white pb-6 text-stone-900">
          <p className="text-[0.72rem] font-semibold uppercase tracking-[0.3em] text-[#b71c1c]">Account Security</p>
          <div className="mt-3 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <h1 className="text-3xl font-bold sm:text-4xl">Change your password</h1>
            </div>
            <div className="flex flex-wrap gap-3">
              <Button
                type="button"
                variant="secondary"
                onClick={() => navigate('/account')}
                className="rounded-none bg-[#f8c235] text-black hover:bg-[#e6b32e]"
              >
                <ArrowLeft className="mr-2 h-4 w-4" />
                Back to Account
              </Button>
            </div>
          </div>
        </div>

        <div className="mx-auto w-full max-w-2xl">
          <div className="rounded-none border border-stone-300 bg-white p-6 sm:p-8">
            <div className="mb-6 flex items-start gap-3">
              <div className="rounded-none bg-[#c8a43a]/18 p-3 text-[#6b5310]">
                <KeyRound className="h-5 w-5" />
              </div>
              <div>
                <h2 className="text-xl font-bold text-stone-900">Password Details</h2>
                <p className="mt-1 text-sm text-stone-600">
                  Use your current password to confirm the change, then choose a new one with at least eight characters.
                </p>
              </div>
            </div>

            <form onSubmit={handleSubmit} className="space-y-5">
              <div>
                <Label htmlFor="current-password" className="text-stone-900">Current Password</Label>
                <Input
                  id="current-password"
                  type="password"
                  autoComplete="current-password"
                  value={currentPassword}
                  onChange={(event) => setCurrentPassword(event.target.value)}
                  className="mt-1 rounded-none border-stone-300 bg-white text-black"
                  required
                />
              </div>

              <div>
                <Label htmlFor="new-password" className="text-stone-900">New Password</Label>
                <Input
                  id="new-password"
                  type="password"
                  autoComplete="new-password"
                  value={newPassword}
                  onChange={(event) => setNewPassword(event.target.value)}
                  className="mt-1 rounded-none border-stone-300 bg-white text-black"
                  required
                />
                <p className={`mt-2 text-sm ${passwordStatus.tone}`}>{passwordStatus.label}</p>
              </div>

              <div>
                <Label htmlFor="confirm-password" className="text-stone-900">Confirm New Password</Label>
                <Input
                  id="confirm-password"
                  type="password"
                  autoComplete="new-password"
                  value={confirmPassword}
                  onChange={(event) => setConfirmPassword(event.target.value)}
                  className="mt-1 rounded-none border-stone-300 bg-white text-black"
                  required
                />
                {confirmPassword ? (
                  <p className={`mt-2 text-sm ${passwordsMatch ? 'text-emerald-700' : 'text-[#b71c1c]'}`}>
                    {passwordsMatch ? 'Passwords match.' : 'Passwords do not match yet.'}
                  </p>
                ) : null}
              </div>

              <div className="flex flex-col gap-3 pt-2 sm:flex-row">
                <Button
                  type="submit"
                  disabled={busy}
                  className="h-12 flex-1 rounded-none bg-[#b71c1c] text-white hover:bg-[#8f1515]"
                >
                  {busy ? 'Updating password...' : 'Save New Password'}
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => navigate('/profile')}
                  className="h-12 flex-1 rounded-none border-stone-300 text-black hover:bg-stone-100"
                >
                  Return to Profile
                </Button>
              </div>
            </form>
          </div>


        </div>
      </motion.div>
    </div>
  );
};

export default ChangePasswordPage;
