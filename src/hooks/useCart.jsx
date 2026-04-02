import React, { createContext, useContext, useState, useEffect, useCallback, useMemo } from 'react';
import { formatCurrency } from '@/api/EcommerceApi';

const CartContext = createContext();

const CART_STORAGE_KEY = 'e-commerce-cart';
const SIGNUP_CART_STORAGE_KEY = 'aac-membership-signup-cart';
const PORTAL_SIGNUP_PREFIX = 'portal-signup-';

const usdVariant = (id, title, cents) => ({
  id,
  title,
  price_in_cents: cents,
  sale_price_in_cents: null,
  currency_info: { code: 'USD', symbol: '$' },
  manage_inventory: false,
});

/** Build line items for membership signup / renewal checkout (not stored in e-commerce cart). */
export const buildSignupCartLineItems = ({ membershipLabel, membershipCents, donationUsd }) => {
  const next = [
    {
      isPortalLine: true,
      quantity: 1,
      product: { title: 'AAC Membership' },
      variant: usdVariant(`${PORTAL_SIGNUP_PREFIX}membership`, membershipLabel, membershipCents),
    },
  ];
  if (donationUsd > 0) {
    next.push({
      isPortalLine: true,
      quantity: 1,
      product: { title: 'Donation' },
      variant: usdVariant(
        `${PORTAL_SIGNUP_PREFIX}donation`,
        'Member donation',
        Math.round(donationUsd * 100)
      ),
    });
  }
  return next;
};

export const useCart = () => useContext(CartContext);

export const CartProvider = ({ children }) => {
  const [cartItems, setCartItems] = useState(() => {
    try {
      const storedCart = localStorage.getItem(CART_STORAGE_KEY);
      const parsed = storedCart ? JSON.parse(storedCart) : [];
      return Array.isArray(parsed) ? parsed.filter((item) => !item.isPortalLine) : [];
    } catch (error) {
      return [];
    }
  });

  const [signupCartItems, setSignupCartItems] = useState(() => {
    try {
      const raw = localStorage.getItem(SIGNUP_CART_STORAGE_KEY);
      return raw ? JSON.parse(raw) : [];
    } catch (error) {
      return [];
    }
  });

  useEffect(() => {
    localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(cartItems));
  }, [cartItems]);

  useEffect(() => {
    localStorage.setItem(SIGNUP_CART_STORAGE_KEY, JSON.stringify(signupCartItems));
  }, [signupCartItems]);

  const addToCart = useCallback((product, variant, quantity, availableQuantity) => {
    return new Promise((resolve, reject) => {
      if (variant.manage_inventory) {
        const existingItem = cartItems.find(item => item.variant.id === variant.id);
        const currentCartQuantity = existingItem ? existingItem.quantity : 0;
        if ((currentCartQuantity + quantity) > availableQuantity) {
          const error = new Error(`Not enough stock for ${product.title} (${variant.title}). Only ${availableQuantity} left.`);
          reject(error);
          return;
        }
      }

      setCartItems(prevItems => {
        const existingItem = prevItems.find(item => item.variant.id === variant.id);
        if (existingItem) {
          return prevItems.map(item =>
            item.variant.id === variant.id
              ? { ...item, quantity: item.quantity + quantity }
              : item
          );
        }
        return [...prevItems, { product, variant, quantity }];
      });
      resolve();
    });
  }, [cartItems]);

  const removeFromCart = useCallback((variantId) => {
    setCartItems(prevItems => prevItems.filter(item => item.variant.id !== variantId));
  }, []);

  const updateQuantity = useCallback((variantId, quantity) => {
    setCartItems(prevItems =>
      prevItems.map(item =>
        item.variant.id === variantId ? { ...item, quantity } : item
      )
    );
  }, []);

  const clearCart = useCallback(() => {
    setCartItems([]);
  }, []);

  const getCartTotal = useCallback(() => {
    if (cartItems.length === 0) {
      return formatCurrency(0, { code: 'USD', symbol: '$' });
    }
    return formatCurrency(cartItems.reduce((total, item) => {
      const price = item.variant.sale_price_in_cents ?? item.variant.price_in_cents;
      return total + price * item.quantity;
    }, 0), cartItems[0].variant.currency_info);
  }, [cartItems]);

  const setSignupCartLines = useCallback(({ membershipLabel, membershipCents, donationUsd }) => {
    setSignupCartItems(buildSignupCartLineItems({ membershipLabel, membershipCents, donationUsd }));
  }, []);

  const clearSignupCart = useCallback(() => {
    setSignupCartItems([]);
  }, []);

  const getSignupCartTotalFormatted = useCallback(() => {
    if (signupCartItems.length === 0) {
      return formatCurrency(0, { code: 'USD', symbol: '$' });
    }
    return formatCurrency(
      signupCartItems.reduce((total, item) => {
        const price = item.variant.sale_price_in_cents ?? item.variant.price_in_cents;
        return total + price * item.quantity;
      }, 0),
      signupCartItems[0].variant.currency_info
    );
  }, [signupCartItems]);

  const value = useMemo(() => ({
    cartItems,
    signupCartItems,
    addToCart,
    removeFromCart,
    updateQuantity,
    clearCart,
    getCartTotal,
    setSignupCartLines,
    clearSignupCart,
    getSignupCartTotalFormatted,
  }), [
    cartItems,
    signupCartItems,
    addToCart,
    removeFromCart,
    updateQuantity,
    clearCart,
    getCartTotal,
    setSignupCartLines,
    clearSignupCart,
    getSignupCartTotalFormatted,
  ]);

  return (
    <CartContext.Provider value={value}>
      {children}
    </CartContext.Provider>
  );
};
