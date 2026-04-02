import React from 'react';
import { BrowserRouter, HashRouter } from 'react-router-dom';

const getRouterMode = () => {
  if (typeof window !== 'undefined' && window.AAC_MEMBER_PORTAL_CONFIG?.routerMode) {
    return window.AAC_MEMBER_PORTAL_CONFIG.routerMode;
  }

  if (import.meta.env.VITE_ROUTER_MODE) {
    return import.meta.env.VITE_ROUTER_MODE;
  }

  if (import.meta.env.VITE_APP_RUNTIME === 'mobile') {
    return 'hash';
  }

  return 'browser';
};

export const AppRouter = ({ children }) => {
  const RouterComponent = getRouterMode() === 'hash' ? HashRouter : BrowserRouter;
  return <RouterComponent>{children}</RouterComponent>;
};
