
import React from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { X, Trash2, Plus, Minus } from 'lucide-react';
import { Button } from '@/components/ui/button';

const CartModal = ({ isOpen, onClose, cartItems, removeFromCart, updateQuantity, checkout, loading }) => {
  const subtotal = cartItems.reduce((sum, item) => sum + parseFloat(item.variant.price.amount) * item.quantity, 0);

  const handleCheckout = () => {
    checkout();
  };

  return (
    <AnimatePresence>
      {isOpen && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          className="fixed inset-0 bg-black/80 z-[100] flex items-center justify-center p-4"
          onClick={onClose}
        >
          <motion.div
            initial={{ scale: 0.9, opacity: 0, y: 50 }}
            animate={{ scale: 1, opacity: 1, y: 0 }}
            exit={{ scale: 0.9, opacity: 0, y: 50 }}
            onClick={(e) => e.stopPropagation()}
            className="card-gradient rounded-2xl p-6 max-w-lg w-full border border-stone-200 shadow-2xl relative flex flex-col max-h-[90vh]"
          >
            <div className="flex justify-between items-center mb-6">
              <h3 className="text-2xl font-bold text-black">Shopping Cart</h3>
              <button onClick={onClose} className="text-black/50 hover:text-black transition-colors">
                <X className="w-6 h-6" />
              </button>
            </div>

            {cartItems.length === 0 ? (
              <div className="text-center py-16">
                <p className="text-black/60 text-lg">Your cart is empty.</p>
                <p className="text-black/50">Time to go shopping!</p>
              </div>
            ) : (
              <>
                <div className="flex-grow overflow-y-auto -mr-3 pr-3 space-y-4">
                  {cartItems.map(item => (
                    <div key={item.variant.id} className="flex items-center gap-4 bg-stone-50 border border-stone-200 p-3 rounded-lg">
                      <img src={item.variant.image.src} alt={item.title} className="w-20 h-20 object-cover rounded-md" />
                      <div className="flex-grow">
                        <p className="text-black font-bold">{item.title}</p>
                        <p className="text-[#B71C1C] font-semibold">${parseFloat(item.variant.price.amount).toFixed(2)}</p>
                      </div>
                      <div className="flex items-center gap-2">
                        <Button size="icon" variant="ghost" onClick={() => updateQuantity(item.variant.id, item.quantity - 1)} className="h-8 w-8">
                          <Minus className="w-4 h-4" />
                        </Button>
                        <span className="text-black font-bold w-4 text-center">{item.quantity}</span>
                        <Button size="icon" variant="ghost" onClick={() => updateQuantity(item.variant.id, item.quantity + 1)} className="h-8 w-8">
                          <Plus className="w-4 h-4" />
                        </Button>
                      </div>
                      <Button size="icon" variant="ghost" onClick={() => removeFromCart(item.variant.id)} className="text-red-500 hover:bg-red-500/10 hover:text-red-400">
                        <Trash2 className="w-5 h-5" />
                      </Button>
                    </div>
                  ))}
                </div>

                <div className="mt-6 pt-6 border-t border-stone-200">
                  <div className="flex justify-between items-center mb-4">
                    <p className="text-black/70 text-lg">Subtotal</p>
                    <p className="text-black text-2xl font-bold">${subtotal.toFixed(2)}</p>
                  </div>
                  <Button onClick={handleCheckout} disabled={loading} className="w-full bg-[#B71C1C] hover:bg-[#D32F2F] text-white h-12 text-lg">
                    {loading ? 'Redirecting to Checkout...' : 'Proceed to Checkout'}
                  </Button>
                </div>
              </>
            )}
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
};

export default CartModal;
