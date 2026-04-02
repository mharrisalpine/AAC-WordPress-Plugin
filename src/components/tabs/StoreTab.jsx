import React from 'react';
import ProductsList from '@/components/ProductsList';
import { motion } from 'framer-motion';
import { ShoppingCart } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useCart } from '@/hooks/useCart';
import { useOutletContext } from 'react-router-dom';
import { useAuth } from '@/hooks/useAuth';
import { useFakePayment } from '@/hooks/useFakePayment';
import { createMerchandisePaymentIntent } from '@/lib/fakePaymentFlows';
import { useToast } from '@/components/ui/use-toast';
import { canAccessStore } from '@/lib/membershipAccess';
import { useNavigate } from 'react-router-dom';

const StoreTab = () => {
  const { setIsCartOpen } = useOutletContext();
  const { cartItems, getCartTotal } = useCart();
  const { profile } = useAuth();
  const { startPaymentFlow } = useFakePayment();
  const { toast } = useToast();
  const navigate = useNavigate();
  const cartItemCount = cartItems.reduce((sum, item) => sum + item.quantity, 0);
  const hasStoreAccess = canAccessStore(profile?.profile_info);

  const handleCheckoutClick = () => {
    if (cartItemCount === 0) {
      setIsCartOpen(true);
      return;
    }

    toast({
      title: 'Opening checkout',
      description: 'Taking you to the fake credit card checkout now.',
    });

    startPaymentFlow(createMerchandisePaymentIntent({
      cartItems,
      accountInfo: profile?.account_info || {},
    }));
  };

  if (!hasStoreAccess) {
    return (
      <div className="py-6">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5 }}
          className="max-w-2xl rounded-[28px] border border-stone-200 bg-white/75 p-8 text-center"
        >
          <h1 className="text-4xl font-bold text-black">AAC Store</h1>
          <p className="mt-4 text-lg text-black/75">
            Free memberships preview the portal, but member store access begins with a paid AAC membership.
          </p>
          <Button
            type="button"
            onClick={() => navigate('/membership')}
            className="mt-6 bg-[#b71c1c] hover:bg-[#8f1515] text-white"
          >
            Manage Membership
          </Button>
        </motion.div>
      </div>
    );
  }

  return (
    <div className="py-6">
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5 }}
        className="space-y-6"
      >
        <div className="mb-8">
          <h1 className="text-4xl font-bold text-black">AAC Store</h1>
          <p className="text-lg text-black/75">Official Gear & Merchandise</p>
        </div>

        <div className="rounded-[24px] border border-stone-200 bg-white/60 px-5 py-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p className="text-sm uppercase tracking-[0.25em] text-[#a07f21] mb-1">Cart Checkout</p>
            <p className="text-black/80">
              {cartItemCount > 0
                ? `${cartItemCount} item${cartItemCount === 1 ? '' : 's'} in cart • ${getCartTotal()}`
                : 'Add gear to your cart, then open checkout with the fake card processor.'}
            </p>
          </div>
          <Button
            type="button"
            onClick={handleCheckoutClick}
            className="bg-[#b71c1c] hover:bg-[#8f1515] text-white"
          >
            <ShoppingCart className="w-4 h-4 mr-2" />
            {cartItemCount > 0 ? 'Checkout Cart' : 'Open Cart'}
          </Button>
        </div>
        <ProductsList />
      </motion.div>
    </div>
  );
};

export default StoreTab;
