import React from "react";
import { createRoot } from "react-dom/client";
import AssessmentAppProduction from "./components/AssessmentAppProduction";
import AppErrorBoundary from "./components/shared/AppErrorBoundary";
import { BrandProvider } from "./branding/BrandContext";
import "./styles.css";
import "./brand-overrides.css";
import "./theme-runtime.css";
import "./admin-production.css";
import "./admin-extended.css";
import "./admin-final-polish.css";
import "./production-final.css";
import "./admin-security-polish.css";
import "./admin-support.css";
import "./questionnaire-process.css";
import "./questionnaire-latest.css";
import "./uat-layout-fix.css";
import "./v4-mobile-burnin.css";
import "./report-flow.css";
import "./report-editorial-v4.css";
import "./report-full-hero-v4.css";

function CheckoutReturnRecovery() {
  React.useEffect(() => {
    const recover = () => {
      if (!window.location.pathname.startsWith("/report/")) return;
      const buttons = Array.from(document.querySelectorAll("button"));
      const stale = buttons.some(button => /Opening (checkout|secure checkout)…/i.test(button.textContent || ""));
      if (stale) window.location.reload();
    };
    const onVisibility = () => { if (document.visibilityState === "visible") recover(); };
    window.addEventListener("pageshow", recover);
    window.addEventListener("focus", recover);
    document.addEventListener("visibilitychange", onVisibility);
    return () => {
      window.removeEventListener("pageshow", recover);
      window.removeEventListener("focus", recover);
      document.removeEventListener("visibilitychange", onVisibility);
    };
  }, []);
  return null;
}

function ServiceWorkerUpdate() {
  const [updateAvailable, setUpdateAvailable] = React.useState(false);

  React.useEffect(() => {
    const enabled = import.meta.env.VITE_ENABLE_SW === "true";
    const productionApi = import.meta.env.VITE_API_MODE === "production";
    const correctHost = window.location.hostname === "v4.atomglobal.com";

    if (!enabled || !productionApi || !correctHost || !("serviceWorker" in navigator)) {
      navigator.serviceWorker?.getRegistrations?.().then(registrations => {
        if (!correctHost || !enabled) registrations.forEach(registration => registration.unregister());
      });
      return undefined;
    }

    let mounted = true;
    const hadController = Boolean(navigator.serviceWorker.controller);
    const showUpdate = () => { if (mounted && hadController) setUpdateAvailable(true); };
    const onControllerChange = () => showUpdate();

    navigator.serviceWorker.addEventListener("controllerchange", onControllerChange);
    navigator.serviceWorker.register(`/sw.js?v=${encodeURIComponent(__APP_BUILD_VERSION__)}`, { updateViaCache: "none" })
      .then(registration => {
        const watchInstallingWorker = worker => {
          if (!worker) return;
          worker.addEventListener("statechange", () => {
            if (worker.state === "installed") showUpdate();
          });
        };
        watchInstallingWorker(registration.installing);
        registration.addEventListener("updatefound", () => watchInstallingWorker(registration.installing));
        return registration.update();
      })
      .catch(error => { if (import.meta.env.DEV) console.error("Service worker registration failed", error); });

    return () => {
      mounted = false;
      navigator.serviceWorker.removeEventListener("controllerchange", onControllerChange);
    };
  }, []);

  if (!updateAvailable) return null;
  return <aside className="update-notice" role="status">
    <span>A new version is available. Reload.</span>
    <button type="button" onClick={() => window.location.reload()}>Reload</button>
  </aside>;
}

createRoot(document.getElementById("root")).render(
  <React.StrictMode>
    <AppErrorBoundary>
      <BrandProvider>
        <AssessmentAppProduction />
        <CheckoutReturnRecovery />
        <ServiceWorkerUpdate />
      </BrandProvider>
    </AppErrorBoundary>
  </React.StrictMode>,
);
