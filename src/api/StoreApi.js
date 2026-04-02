import { apiRequest } from '@/lib/apiClient';
import { getCommerceProvider } from '@/lib/backendConfig';
import {
  formatCurrency,
  getProduct as getEmbeddedProduct,
  getProductQuantities as getEmbeddedProductQuantities,
  getProducts as getEmbeddedProducts,
} from '@/api/EcommerceApi';

const shouldUseBackendStore = () => ['backend', 'shopify'].includes(getCommerceProvider());

const buildQueryString = (params = {}) => {
  const searchParams = new URLSearchParams();

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') {
      return;
    }

    if (Array.isArray(value)) {
      value.forEach((item) => searchParams.append(key, item));
      return;
    }

    searchParams.set(key, String(value));
  });

  const queryString = searchParams.toString();
  return queryString ? `?${queryString}` : '';
};

export { formatCurrency };

export async function getProducts(params = {}) {
  if (!shouldUseBackendStore()) {
    return getEmbeddedProducts(params);
  }

  return apiRequest(`/store/products${buildQueryString(params)}`);
}

export async function getProduct(id, params = {}) {
  if (!shouldUseBackendStore()) {
    return getEmbeddedProduct(id, params);
  }

  return apiRequest(`/store/products/${id}${buildQueryString(params)}`);
}

export async function getProductQuantities(params = {}) {
  if (!shouldUseBackendStore()) {
    return getEmbeddedProductQuantities(params);
  }

  return apiRequest(`/store/products/quantities${buildQueryString(params)}`);
}
