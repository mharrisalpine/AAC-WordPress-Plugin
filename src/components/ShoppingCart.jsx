import React from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { ShoppingCart as ShoppingCartIcon, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/api/EcommerceApi';

const lineDisplayPrice = (item) => {
  if (item.isPortalLine) {
    const cents = item.variant.sale_price_in_cents ?? item.variant.price_in_cents ?? 0;
    return formatCurrency(cents, item.variant.currency_info || { code: 'USD', symbol: '$' });
  }
  return item.variant.sale_price_formatted || item.variant.price_formatted;
};

const ShoppingCart = ({ isCartOpen, setIsCartOpen, cartItems, removeFromCart, updateQuantity, getCartTotal, handleCheckout }) => {
  return (
    <AnimatePresence>
      {isCartOpen && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          className="fixed inset-0 bg-stone-900/30 z-50"
          onClick={() => setIsCartOpen(false)}
        >
          <motion.div
            initial={{ x: '100%' }}
            animate={{ x: 0 }}
            exit={{ x: '100%' }}
            transition={{ type: 'spring', stiffness: 300, damping: 30 }}
            className="absolute right-0 top-0 h-full w-full max-w-md bg-[#faf8f5] border-l border-stone-200 shadow-2xl flex flex-col"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="flex items-center justify-between p-6 border-b border-stone-200">
              <h2 className="text-2xl font-bold text-stone-900">Shopping Cart</h2>
              <Button onClick={() => setIsCartOpen(false)} variant="ghost" size="icon" className="text-stone-700 hover:bg-stone-100">
                <X />
              </Button>
            </div>
            <div className="flex-grow p-6 overflow-y-auto space-y-4">
              {cartItems.length === 0 ? (
                <div className="text-center text-stone-600 h-full flex flex-col items-center justify-center">
                  <ShoppingCartIcon size={48} className="mb-4" />
                  <p>Your cart is empty.</p>
                </div>
              ) : (
                cartItems.map(item => (
                  <div key={item.variant.id} className="flex items-center gap-4 bg-white p-3 rounded-lg border border-stone-200 shadow-sm">
                    {item.product?.image ? (
                      <img src={item.product.image} alt={item.product.title} className="w-20 h-20 object-cover rounded-md" />
                    ) : (
                      <div className="w-20 h-20 rounded-md bg-[rgba(200,164,58,0.2)] flex items-center justify-center text-[#8a6d1f] text-xs font-semibold text-center px-1">
                        AAC
                      </div>
                    )}
                    <div className="flex-grow">
                      <h3 className="font-semibold text-stone-900">{item.product.title}</h3>
                      <p className="text-sm text-stone-600">{item.variant.title}</p>
                      <p className="text-sm text-[#8a6d1f] font-bold">
                        {lineDisplayPrice(item)}
                      </p>
                    </div>
                    <div className="flex flex-col items-end gap-2">
                      <div className="flex items-center border border-stone-200 rounded-md">
                        <Button onClick={() => updateQuantity(item.variant.id, Math.max(1, item.quantity - 1))} size="sm" variant="ghost" className="px-2 text-stone-800 hover:bg-stone-100">-</Button>
                        <span className="px-2 text-stone-900">{item.quantity}</span>
                        <Button onClick={() => updateQuantity(item.variant.id, item.quantity + 1)} size="sm" variant="ghost" className="px-2 text-stone-800 hover:bg-stone-100">+</Button>
                      </div>
                      <Button onClick={() => removeFromCart(item.variant.id)} size="sm" variant="ghost" className="text-red-600 hover:text-red-700 text-xs">Remove</Button>
                    </div>
                  </div>
                ))
              )}
            </div>
            {cartItems.length > 0 && (
              <div className="p-6 border-t border-stone-200 bg-[#f3f1ec]">
                <div className="flex justify-between items-center mb-4 text-stone-900">
                  <span className="text-lg font-medium">Total</span>
                  <span className="text-2xl font-bold">{getCartTotal()}</span>
                </div>
                <p className="text-sm text-stone-600 mb-4">
                  Checkout uses the demo card processor and prefills billing from your member account.
                </p>
                <Button
                  type="button"
                  onClick={handleCheckout}
                  className="w-full bg-[#b71c1c] hover:bg-[#8f1515] text-[#faf8f5] font-semibold py-3 text-base"
                >
                  Checkout
                </Button>
              </div>
            )}
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
};

export default ShoppingCart;
