import React, { useEffect } from 'react';
import { useOutletContext, useLocation } from 'react-router-dom';
import ProfileTab from '@/components/tabs/ProfileTab';
import RescueTab from '@/components/tabs/RescueTab';
import DiscountsTab from '@/components/tabs/DiscountsTab';
import StoreTab from '@/components/tabs/StoreTab';
import AccountTab from '@/components/tabs/AccountTab';
import PodcastsTab from '@/components/tabs/PodcastsTab';
import ShoppingCart from '@/components/ShoppingCart';
import { useAuth } from '@/hooks/useAuth';
import { useCart } from '@/hooks/useCart';
import { useFakePayment } from '@/hooks/useFakePayment';
import { useToast } from '@/components/ui/use-toast';
import { createMerchandisePaymentIntent } from '@/lib/fakePaymentFlows';

const MemberPortal = ({ storeTab }) => {
  const { user, profile, loading } = useAuth();
  const { isCartOpen, setIsCartOpen, activeTab, setActiveTab } = useOutletContext();
  const { cartItems, removeFromCart, updateQuantity, getCartTotal } = useCart();
  const { startPaymentFlow } = useFakePayment();
  const { toast } = useToast();
  const location = useLocation();

  useEffect(() => {
    const tabFromUrl = location.pathname.substring(1);
    if (['rescue', 'discounts', 'store', 'podcasts', 'account'].includes(tabFromUrl)) {
      setActiveTab(tabFromUrl);
    } else if (storeTab) {
      setActiveTab(storeTab);
    } else if (location.pathname === '/') {
      setActiveTab('profile');
    }
  }, [location.pathname, storeTab, setActiveTab]);

  const handleCheckout = async () => {
    if (cartItems.length === 0) {
      toast({
        title: "Your cart is empty",
        description: "Add some products to your cart before checking out.",
        variant: "destructive",
      });
      return;
    }

    setIsCartOpen(false);
    startPaymentFlow(createMerchandisePaymentIntent({
      cartItems,
      accountInfo: profile?.account_info || {},
    }));
  };

  const renderActiveTab = () => {
    if (loading || !profile) {
      return <div className="text-stone-800 text-center pt-10">Loading profile...</div>;
    }
    switch (activeTab) {
      case 'profile':
        return <ProfileTab profile={profile} />;
      case 'rescue':
        return <RescueTab profile={profile} />;
      case 'discounts':
        return <DiscountsTab profile={profile} />;
      case 'store':
        return <StoreTab />;
      case 'account':
        return <AccountTab profile={profile} />;
      case 'podcasts':
        return <PodcastsTab />;
      default:
        return <ProfileTab profile={profile} />;
    }
  };

  return (
    <div>
      {renderActiveTab()}
      <ShoppingCart 
        isCartOpen={isCartOpen} 
        setIsCartOpen={setIsCartOpen}
        cartItems={cartItems}
        removeFromCart={removeFromCart}
        updateQuantity={updateQuantity}
        getCartTotal={getCartTotal}
        handleCheckout={handleCheckout}
      />
    </div>
  );
};

export default MemberPortal;
