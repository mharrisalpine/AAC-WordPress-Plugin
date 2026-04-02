import React from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { X } from 'lucide-react';

const RescuePolicyModal = ({ isOpen, onClose, policy }) => {
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
            className="card-gradient rounded-2xl p-6 max-w-2xl w-full border border-stone-200 shadow-2xl relative flex flex-col max-h-[90vh]"
          >
            <div className="flex justify-between items-center mb-6 pb-4 border-b border-stone-200">
              <h3 className="text-2xl font-bold text-black">{policy.title}</h3>
              <button onClick={onClose} className="text-black/50 hover:text-black transition-colors">
                <X className="w-6 h-6" />
              </button>
            </div>

            <div className="flex-grow overflow-y-auto prose prose-neutral max-w-none text-black prose-p:text-black prose-headings:text-black prose-strong:text-black">
              {policy.content.map((section, index) => (
                <div key={index}>
                  <h4 className="text-xl font-bold text-[#B71C1C] mt-4">{section.heading}</h4>
                  <p>{section.text}</p>
                </div>
              ))}
            </div>
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
};

export default RescuePolicyModal;