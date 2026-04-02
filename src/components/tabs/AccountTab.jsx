
import React, { useState, useEffect, useCallback } from 'react';
import { motion } from 'framer-motion';
import { Camera, KeyRound, Receipt, User } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toast } from '@/components/ui/use-toast';
import ChangePhotoModal from '@/components/ChangePhotoModal';
import { useAuth } from '@/hooks/useAuth';
import { useMembershipActions } from '@/hooks/useMembershipActions';
import { getMemberTransactions } from '@/lib/memberApi';
import { getMembershipBenefits, formatDollars } from '@/lib/fakePaymentFlows';
import { formatMagazineSubscriptions, getFullName, normalizeAccountInfo } from '@/lib/memberProfile';
import { getMembershipStatus, isMembershipActive } from '@/lib/membershipStatus';
import { cn } from '@/lib/utils';
import {
  listMemberTransactions,
  subscribeMemberTransactions,
  MEMBER_TRANSACTIONS_STORAGE_KEY,
} from '@/lib/transactions';

const AccountTab = ({ profile }) => {
  const navigate = useNavigate();
  const { user, updateProfile } = useAuth();
  const [accountData, setAccountData] = useState(null);
  const [isPhotoModalOpen, setIsPhotoModalOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [localTransactions, setLocalTransactions] = useState([]);
  const [remoteTransactions, setRemoteTransactions] = useState([]);

  const refreshLocalTransactions = useCallback(() => {
    if (user?.id) {
      setLocalTransactions(listMemberTransactions(user.id));
    } else {
      setLocalTransactions([]);
    }
  }, [user?.id]);

  useEffect(() => {
    refreshLocalTransactions();
    const unsubscribe = subscribeMemberTransactions(refreshLocalTransactions);
    return unsubscribe;
  }, [refreshLocalTransactions]);

  useEffect(() => {
    const onStorage = (e) => {
      if (e.key === MEMBER_TRANSACTIONS_STORAGE_KEY) {
        refreshLocalTransactions();
      }
    };
    window.addEventListener('storage', onStorage);
    return () => window.removeEventListener('storage', onStorage);
  }, [refreshLocalTransactions]);

  useEffect(() => {
    let cancelled = false;

    if (!user?.id) {
      setRemoteTransactions([]);
      return () => {
        cancelled = true;
      };
    }

    const loadRemoteTransactions = async () => {
      try {
        const data = await getMemberTransactions();
        if (!cancelled) {
          setRemoteTransactions(Array.isArray(data?.transactions) ? data.transactions : []);
        }
      } catch (_error) {
        if (!cancelled) {
          setRemoteTransactions([]);
        }
      }
    };

    loadRemoteTransactions();

    return () => {
      cancelled = true;
    };
  }, [user?.id]);
  const { openMembershipAction, getMembershipActionUrl, hasManagedMembershipUrls } = useMembershipActions();

  const transactions = React.useMemo(() => {
    const combined = [...remoteTransactions, ...localTransactions];
    const seen = new Set();

    return combined
      .filter((transaction) => {
        const dedupeKey = transaction.referenceId || transaction.id;
        if (!dedupeKey || seen.has(dedupeKey)) {
          return false;
        }

        seen.add(dedupeKey);
        return true;
      })
      .sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
  }, [localTransactions, remoteTransactions]);

  useEffect(() => {
    if (profile && profile.account_info) {
      setAccountData(normalizeAccountInfo(profile.account_info));
    }
  }, [profile]);

  const handleSave = async () => {
    const nextAccountData = accountData;
    const normalizedAccountData = normalizeAccountInfo(nextAccountData);
    setSaving(true);
    try {
      setAccountData(normalizedAccountData);
      await updateProfile({ account_info: normalizedAccountData });
      toast({
        title: "✅ Settings Saved!",
        description: "Your account settings have been updated.",
      });
    } finally {
      setSaving(false);
    }
  };

  const handlePhotoSave = async (newUrl) => {
    if (newUrl) {
      const updatedData = normalizeAccountInfo({ ...accountData, photo_url: newUrl });
      setAccountData(updatedData);
      await updateProfile({ account_info: updatedData });
      toast({
        title: "📸 Photo Updated!",
        description: "Your profile photo has been changed.",
      });
    }
  };

  const handleRenew = () => {
    void openMembershipAction('renew', { targetTier: profile?.profile_info?.tier || 'Partner' });
  };

  const handleCancel = async () => {
    if (hasManagedMembershipUrls) {
      if (getMembershipActionUrl('cancel')) {
        void openMembershipAction('cancel');
        return;
      }

      toast({
        variant: 'destructive',
        title: 'Cancellation unavailable',
        description: 'PMPro cancellation is not configured yet for this membership. Please open your membership account to continue.',
      });
      void openMembershipAction('manage');
      return;
    }

    if (getMembershipActionUrl('cancel')) {
      void openMembershipAction('cancel');
      return;
    }

    const nextAccountData = { ...accountData, auto_renew: false };
    setAccountData(nextAccountData);
    await updateProfile({
      account_info: nextAccountData,
      profile_info: {
        ...(profile?.profile_info || {}),
        tier: '',
        renewal_date: '',
        status: 'Inactive',
      },
      benefits_info: getMembershipBenefits('Supporter'),
    });
    toast({
      title: 'Membership canceled',
      description: 'Your membership is now inactive and Redpoint benefits have been removed.',
    });
  };

  const membershipStatus = getMembershipStatus(profile?.profile_info);
  const membershipActive = isMembershipActive(profile?.profile_info);

  const transactionGroups = ['Membership', 'Donation', 'Merchandise', 'Events', 'Lodging'].map((kind) => ({
    kind,
    entries: transactions.filter((transaction) => transaction.kind === kind),
  }));

  if (!accountData) return <div className="text-black text-center pt-10">Loading account details...</div>;

  return (
    <>
      <div className="py-6">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5 }}
        >
          <h2 className="text-3xl font-bold mb-6 text-black">Account Settings</h2>

          <div className="max-w-2xl mx-auto space-y-6">
            <div className="card-gradient rounded-2xl border border-stone-200 p-6">
              <h3 className="text-xl font-bold text-black mb-4">Account status</h3>
              <div className="space-y-3">
                <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                  <span className="text-black font-medium">Member portal</span>
                  <span className="text-emerald-800 font-semibold">Active</span>
                </div>
                <div
                  className={cn(
                    'flex flex-wrap items-center justify-between gap-2 rounded-xl border px-4 py-3',
                    membershipActive
                      ? 'border-emerald-200 bg-emerald-50'
                      : 'border-amber-200 bg-amber-50/90',
                  )}
                >
                  <span className="text-black font-medium">Membership</span>
                  <span
                    className={cn(
                      'font-semibold',
                      membershipActive ? 'text-emerald-800' : 'text-amber-900',
                    )}
                  >
                    {membershipActive ? 'Active' : membershipStatus}
                  </span>
                </div>
                <p className="text-sm text-black/60">
                  Your portal login is active. Membership status reflects your current AAC membership record.
                </p>
              </div>
            </div>

            {/* Profile Photo */}
            <div className="card-gradient rounded-2xl p-6 border border-stone-200">
              <h3 className="text-xl font-bold text-black mb-4">Profile Photo</h3>
              <div className="flex items-center gap-6">
                <div className="relative">
                  {accountData.photo_url ? (
                    <img
                      src={accountData.photo_url}
                      alt="Profile"
                      className="h-24 w-24 rounded-full border-4 border-[#B71C1C] object-cover"
                    />
                  ) : (
                    <div
                      className="flex h-24 w-24 items-center justify-center rounded-full border-4 border-dashed border-[#B71C1C]/50 bg-stone-100"
                      aria-hidden
                    >
                      <User className="h-11 w-11 text-stone-400" strokeWidth={1.5} />
                    </div>
                  )}
                  <button
                    type="button"
                    onClick={() => setIsPhotoModalOpen(true)}
                    className="absolute bottom-0 right-0 bg-[#b71c1c] hover:bg-[#8f1515] text-white rounded-full p-2 transition-colors"
                    aria-label="Change profile photo"
                  >
                    <Camera className="w-4 h-4" />
                  </button>
                </div>
                <div>
                  <p className="text-black font-medium mb-1">{getFullName(accountData)}</p>
                  <p className="text-black/60 text-sm">Click camera icon to update photo</p>
                </div>
              </div>
            </div>

            {/* Personal Information */}
            <div className="card-gradient rounded-2xl p-6 border border-stone-200">
              <h3 className="text-xl font-bold text-black mb-4">Personal Information</h3>
              <div className="space-y-4">
                <div>
                  <Label htmlFor="email" className="text-black">Email</Label>
                  <Input
                    id="email"
                    type="email"
                    value={accountData.email || ''}
                    onChange={(e) => setAccountData({ ...accountData, email: e.target.value })}
                    className="bg-white border-[#d9d9d9] text-black mt-1"
                  />
                </div>

                <div>
                  <Label htmlFor="phone" className="text-black">Phone</Label>
                  <Input
                    id="phone"
                    value={accountData.phone || ''}
                    onChange={(e) => setAccountData({ ...accountData, phone: e.target.value })}
                    className="bg-white border-[#d9d9d9] text-black mt-1"
                  />
                </div>

                <div>
                  <Label htmlFor="street" className="text-black">Street Address</Label>
                  <Input
                    id="street"
                    value={accountData.street || ''}
                    onChange={(e) => setAccountData({ ...accountData, street: e.target.value })}
                    className="bg-white border-[#d9d9d9] text-black mt-1"
                  />
                </div>

                <div>
                  <Label htmlFor="address2" className="text-black">Address Line 2</Label>
                  <Input
                    id="address2"
                    value={accountData.address2 || ''}
                    onChange={(e) => setAccountData({ ...accountData, address2: e.target.value })}
                    className="bg-white border-[#d9d9d9] text-black mt-1"
                  />
                </div>
                
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <Label htmlFor="city" className="text-black">City</Label>
                        <Input id="city" value={accountData.city || ''} onChange={(e) => setAccountData({...accountData, city: e.target.value})} className="bg-white border-[#d9d9d9] text-black mt-1"/>
                    </div>
                    <div>
                        <Label htmlFor="state" className="text-black">State / Province</Label>
                        <Input id="state" value={accountData.state || ''} onChange={(e) => setAccountData({...accountData, state: e.target.value})} className="bg-white border-[#d9d9d9] text-black mt-1"/>
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <Label htmlFor="zip" className="text-black">ZIP / Postal Code</Label>
                        <Input id="zip" value={accountData.zip || ''} onChange={(e) => setAccountData({...accountData, zip: e.target.value})} className="bg-white border-[#d9d9d9] text-black mt-1"/>
                    </div>
                    <div>
                        <Label htmlFor="country" className="text-black">Country</Label>
                        <Input id="country" value={accountData.country || ''} onChange={(e) => setAccountData({...accountData, country: e.target.value})} className="bg-white border-[#d9d9d9] text-black mt-1"/>
                    </div>
                </div>

                <div>
                  <Label htmlFor="size" className="text-black">T-Shirt Size</Label>
                  <select
                    id="size"
                    value={accountData.size || 'M'}
                    onChange={(e) => setAccountData({ ...accountData, size: e.target.value })}
                    className="w-full bg-white border border-[#d9d9d9] text-black rounded-md px-3 py-2 mt-1"
                  >
                    <option value="XS">XS</option>
                    <option value="S">S</option>
                    <option value="M">M</option>
                    <option value="L">L</option>
                    <option value="XL">XL</option>
                    <option value="XXL">XXL</option>
                  </select>
                </div>
              </div>
            </div>

            {/* Preferences */}
            <div className="card-gradient rounded-2xl p-6 border border-stone-200">
              <h3 className="text-xl font-bold text-black mb-4">Preferences</h3>
              <div className="space-y-4">
                <div className="flex items-center justify-between bg-[#0B0B0B] rounded-lg p-4">
                  <div>
                    <p className="text-white font-medium">Publication Format</p>
                    <p className="text-[#999999] text-sm">Choose how you receive AAC publications</p>
                  </div>
                  <select
                    value={accountData.publication_pref || 'Digital'}
                    onChange={(e) => setAccountData({ ...accountData, publication_pref: e.target.value })}
                    className="bg-white border border-[#d9d9d9] text-black rounded-md px-3 py-2"
                  >
                    <option value="Print">Print</option>
                    <option value="Digital">Digital</option>
                  </select>
                </div>

                <div className="flex items-center justify-between bg-[#0B0B0B] rounded-lg p-4">
                  <div>
                    <p className="text-white font-medium">Guide Preference</p>
                    <p className="text-[#999999] text-sm">Choose how you receive AAC guide content</p>
                  </div>
                  <select
                    value={accountData.guidebook_pref || 'Digital'}
                    onChange={(e) => setAccountData({ ...accountData, guidebook_pref: e.target.value })}
                    className="bg-white border border-[#d9d9d9] text-black rounded-md px-3 py-2"
                  >
                    <option value="Print">Print</option>
                    <option value="Digital">Digital</option>
                  </select>
                </div>

                <div className="flex items-center justify-between bg-[#0B0B0B] rounded-lg p-4">
                  <div>
                    <p className="text-white font-medium">Magazine Subscriptions</p>
                    <p className="text-[#999999] text-sm">Managed during membership checkout and stored on your member record</p>
                  </div>
                  <span className="text-right text-sm font-medium text-white">
                    {formatMagazineSubscriptions(accountData.magazine_subscriptions)}
                  </span>
                </div>

              </div>
            </div>

            <div className="card-gradient rounded-2xl p-6 border border-stone-200">
              <h3 className="text-xl font-bold text-black mb-4">Security</h3>
              <div className="bg-[#0B0B0B] rounded-lg p-4 flex items-center justify-between gap-4">
                <div className="flex items-center gap-3">
                  <KeyRound className="w-6 h-6 text-[#B71C1C]" />
                  <div>
                    <p className="text-white font-medium">Password</p>
                    <p className="text-[#999999] text-sm">Change your AAC portal password without leaving the app</p>
                  </div>
                </div>
                <Button
                  onClick={() => navigate('/change-password')}
                  variant="secondary"
                  className="text-black hover:bg-[#a07f21]"
                >
                  Change
                </Button>
              </div>
            </div>

            <div className="card-gradient rounded-2xl border border-stone-200 p-6">
              <div className="flex items-center gap-3 mb-4">
                <Receipt className="h-6 w-6 text-[#c8a43a]" />
                <h3 className="text-xl font-bold text-black">Transaction register</h3>
              </div>
              <p className="mb-5 text-sm text-black/60">
                Purchases and payments you complete in this portal appear here. No sample transactions are added.
              </p>
              <div className="space-y-5">
                {transactionGroups.map((group) => (
                  <div key={group.kind}>
                    <p className="mb-3 text-sm uppercase tracking-[0.25em] text-[#c8a43a]">{group.kind}</p>
                    {group.entries.length === 0 ? (
                      <div className="rounded-2xl border border-[rgba(255,255,255,0.08)] bg-[#0B0B0B] px-4 py-3 text-sm text-gray-400">
                        No {group.kind.toLowerCase()} transactions yet.
                      </div>
                    ) : (
                      <div className="space-y-3">
                        {group.entries.map((transaction) => (
                          <div
                            key={transaction.id}
                            className="flex flex-col gap-3 rounded-2xl border border-[rgba(255,255,255,0.08)] bg-[#0B0B0B] px-4 py-4 md:flex-row md:items-center md:justify-between"
                          >
                            <div>
                              <p className="font-medium text-white">{transaction.description}</p>
                              <p className="text-sm text-gray-400">
                                {new Date(transaction.createdAt).toLocaleString()} • {transaction.status}
                              </p>
                            </div>
                            <div className="text-left md:text-right">
                              <p className="text-lg font-bold text-white">{formatDollars(transaction.amount)}</p>
                              <p className="text-xs uppercase tracking-[0.2em] text-[#f1d37b]">{transaction.kind}</p>
                            </div>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </div>

            {/* Action Buttons */}
            <div className="space-y-3">
              <Button
                onClick={handleSave}
                disabled={saving}
                className="w-full bg-[#b71c1c] hover:bg-[#8f1515] text-white h-12 text-lg"
              >
                {saving ? 'Saving...' : 'Save Changes'}
              </Button>

              {!hasManagedMembershipUrls ? (
                <Button
                  onClick={handleRenew}
                  className="w-full bg-[#c8a43a] hover:bg-[#a07f21] text-black h-12 text-lg"
                >
                  Renew Membership
                </Button>
              ) : null}

              <Button
                onClick={handleCancel}
                variant="outline"
                className="w-full border-stone-400 text-black hover:bg-stone-100 h-12 text-lg"
              >
                Cancel Membership
              </Button>
            </div>
          </div>
        </motion.div>
      </div>
      <ChangePhotoModal 
        isOpen={isPhotoModalOpen}
        onClose={() => setIsPhotoModalOpen(false)}
        onSave={handlePhotoSave}
      />
    </>
  );
};

export default AccountTab;
