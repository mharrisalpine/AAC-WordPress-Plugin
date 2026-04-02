import React, { useState } from 'react';
import { Helmet } from 'react-helmet';
import { motion } from 'framer-motion';
import { Mail, Send, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/components/ui/use-toast';
import { getFullName } from '@/lib/memberProfile';
import { submitContactMessage } from '@/lib/memberApi';

const ContactPage = () => {
  const { user, profile } = useAuth();
  const { toast } = useToast();
  const [message, setMessage] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!message.trim()) {
      toast({
        variant: "destructive",
        title: "Message is empty",
        description: "Please write a message before sending.",
      });
      return;
    }

    setLoading(true);

    try {
      await submitContactMessage({
        name: getFullName(profile?.account_info) || user?.email,
        email: user?.email,
        message,
      });
      toast({
        title: "Message Sent! 🚀",
        description: "We've received your message and will get back to you soon.",
      });
      setMessage('');
    } catch (error) {
      toast({
        variant: "destructive",
        title: "Failed to send message",
        description: error.message,
      });
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <Helmet>
        <title>Contact Us - American Alpine Club</title>
        <meta name="description" content="Get in touch with the American Alpine Club membership team." />
      </Helmet>
      <div className="pt-24 min-h-screen flex items-center justify-center px-4">
        <motion.div
          initial={{ opacity: 0, scale: 0.95 }}
          animate={{ opacity: 1, scale: 1 }}
          transition={{ duration: 0.5 }}
          className="w-full max-w-lg card-gradient border border-stone-200 rounded-[28px] shadow-2xl p-8 md:p-12"
        >
          <div className="text-center">
            <motion.div
              initial={{ scale: 0 }}
              animate={{ scale: 1, rotate: -10 }}
              transition={{ delay: 0.2, type: 'spring', stiffness: 150 }}
              className="inline-block p-4 bg-[#b71c1c] rounded-full mb-6"
            >
              <Mail className="h-12 w-12 text-white" />
            </motion.div>
            <h1 className="text-4xl font-bold text-black mb-4">Contact Us</h1>
            <p className="text-lg text-black/75 mb-8">
              Have questions about your membership or benefits? We're here to help!
            </p>
          </div>
          <form onSubmit={handleSubmit} className="space-y-6">
            <div className="grid w-full items-center gap-1.5">
              <Label htmlFor="name" className="text-black">Name</Label>
              <Input type="text" id="name" value={getFullName(profile?.account_info)} disabled className="bg-white border-[#d9d9d9] text-black" />
            </div>
            <div className="grid w-full items-center gap-1.5">
              <Label htmlFor="email" className="text-black">Email</Label>
              <Input type="email" id="email" value={user?.email || ''} disabled className="bg-white border-[#d9d9d9] text-black" />
            </div>
            <div className="grid w-full items-center gap-1.5">
              <Label htmlFor="message" className="text-black">Message</Label>
              <textarea
                id="message"
                value={message}
                onChange={(e) => setMessage(e.target.value)}
                rows={5}
                className="flex w-full rounded-md border border-[#d9d9d9] bg-white px-3 py-2 text-sm text-black ring-offset-background placeholder:text-[#666666] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#c8a43a] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                placeholder="Type your message here..."
              />
            </div>
            <Button
              type="submit"
              size="lg"
              disabled={loading}
              className="w-full bg-[#b71c1c] text-white font-bold py-3 text-lg hover:bg-[#8f1515] transition-all duration-300 ease-in-out transform hover:scale-105 disabled:opacity-50"
            >
              {loading ? (
                <Loader2 className="mr-3 h-5 w-5 animate-spin" />
              ) : (
                <Send className="mr-3 h-5 w-5" />
              )}
              {loading ? 'Sending...' : 'Send Message'}
            </Button>
          </form>
        </motion.div>
      </div>
    </>
  );
};

export default ContactPage;
