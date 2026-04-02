import { useCallback } from 'react';
import { useNavigate } from 'react-router-dom';

export const useFakePayment = () => {
  const navigate = useNavigate();

  const startPaymentFlow = useCallback((intent) => {
    navigate('/payment', { state: { intent } });
  }, [navigate]);

  return { startPaymentFlow };
};
