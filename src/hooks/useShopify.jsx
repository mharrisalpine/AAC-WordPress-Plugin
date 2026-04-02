
import { useState, useEffect, useCallback } from 'react';
import Client from 'shopify-buy';
import { toast } from '@/components/ui/use-toast';

let client;
const SHOPIFY_DOMAIN = 'aacteststore.myshopify.com';
const SHOPIFY_TOKEN = 'e53234a9b70b58e723223122c813088d';

try {
  if (SHOPIFY_DOMAIN && SHOPIFY_TOKEN) {
    client = Client.buildClient({
      domain: SHOPIFY_DOMAIN,
      storefrontAccessToken: SHOPIFY_TOKEN,
    });
  }
} catch (error) {
  console.error("Error building Shopify client:", error);
  client = null;
}

export const useShopify = () => {
  const [products, setProducts] = useState([]);
  const [cart, setCart] = useState([]);
  const [loading, setLoading] = useState(true);

  const initializeCheckout = useCallback(async () => {
    if (!client) return null;
    try {
      const newCheckout = await client.checkout.create();
      localStorage.setItem('shopify_checkout_id', newCheckout.id);
      return newCheckout;
    } catch (error) {
      console.error("Error initializing checkout:", error);
      toast({
        title: "Checkout Error",
        description: "Could not initialize the shopping cart.",
        variant: "destructive",
      });
      return null;
    }
  }, []);

  useEffect(() => {
    let isMounted = true;

    const fetchAll = async () => {
      if (!client) {
        if (isMounted) {
          setLoading(false);
          toast({
              title: "Shopify Integration Error",
              description: "The Shopify client is not configured. Please check credentials.",
              variant: "destructive",
          });
        }
        return;
      }
      
      setLoading(true);
      try {
        const prods = await client.product.fetchAll();
        if (isMounted) {
          setProducts(prods);
        }

        let checkoutId = localStorage.getItem('shopify_checkout_id');
        let checkout;

        if (checkoutId) {
          try {
            checkout = await client.checkout.fetch(checkoutId);
            if (!checkout || checkout.completedAt) {
              checkout = await initializeCheckout();
            }
          } catch (e) {
            checkout = await initializeCheckout();
          }
        } else {
          checkout = await initializeCheckout();
        }

        if (isMounted && checkout) {
          setCart(checkout.lineItems);
        }
      } catch (error) {
        console.error("Error fetching Shopify data:", error);
        if (isMounted) {
          toast({
            title: "Error Loading Store",
            description: "Could not fetch data from Shopify.",
            variant: "destructive",
          });
        }
      } finally {
        if (isMounted) {
          setLoading(false);
        }
      }
    };

    fetchAll();

    return () => {
      isMounted = false;
    };
  }, [initializeCheckout]);

  const addToCart = async (product) => {
    if (!client) return;
    const checkoutId = localStorage.getItem('shopify_checkout_id');
    if (!checkoutId) return;

    const lineItemsToAdd = [{
      variantId: product.variants[0].id,
      quantity: 1,
    }];

    try {
      const checkout = await client.checkout.addLineItems(checkoutId, lineItemsToAdd);
      setCart(checkout.lineItems);
      toast({
        title: "🛒 Item Added to Cart",
        description: `${product.title} has been added to your cart.`,
      });
    } catch (error) {
      console.error("Error adding to cart:", error);
      toast({
        title: "Error",
        description: "Could not add item to cart.",
        variant: "destructive",
      });
    }
  };

  const removeFromCart = async (variantId) => {
    if (!client) return;
    const checkoutId = localStorage.getItem('shopify_checkout_id');
    if (!checkoutId) return;

    try {
      const checkout = await client.checkout.removeLineItems(checkoutId, [variantId]);
      setCart(checkout.lineItems);
      toast({
        title: "🗑️ Item Removed",
        description: "Item removed from your cart.",
        variant: "destructive"
      });
    } catch (error) {
      console.error("Error removing from cart:", error);
    }
  };

  const updateQuantity = async (variantId, quantity) => {
    if (!client) return;
    if (quantity < 1) {
      removeFromCart(variantId);
      return;
    }
    const checkoutId = localStorage.getItem('shopify_checkout_id');
    if (!checkoutId) return;

    const lineItemToUpdate = [{ id: variantId, quantity: parseInt(quantity, 10) }];

    try {
      const checkout = await client.checkout.updateLineItems(checkoutId, lineItemToUpdate);
      setCart(checkout.lineItems);
    } catch (error) {
      console.error("Error updating quantity:", error);
    }
  };

  const checkout = async () => {
    if (!client) return;
    const checkoutId = localStorage.getItem('shopify_checkout_id');
    if (!checkoutId) return;
    try {
      const checkoutData = await client.checkout.fetch(checkoutId);
      if (checkoutData && checkoutData.webUrl) {
         window.location.href = checkoutData.webUrl;
      }
    } catch (error) {
       console.error("Error during checkout:", error);
    }
  };

  return { products, cart, loading, addToCart, removeFromCart, updateQuantity, checkout };
};
