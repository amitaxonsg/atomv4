import React from "react";
import { api } from "../api/client";
import { stageContent } from "../api/mockData";

const legacyLogoUrl = "/media/brand/atom-global-wordmark.png";
const transparentLogoUrl = "/media/brand/atom-global-wordmark-transparent.svg";

const defaults = {
  branding: {
    canvas: "#F7F4EF", surface: "#FFFFFF", textPrimary: "#211C16", textMuted: "#726A5B",
    border: "#E4DDCF", cta: "#C9A15A", ctaHover: "#AF8540", heart: "#C1443F",
    head: "#6C8FAE", accent: "#C9A15A", navy: "#14141C",
    headingFont: 'Georgia, "Times New Roman", serif', bodyFont: "Arial, Helvetica, sans-serif",
    baseFontSize: "16", cardRadius: "8", buttonRadius: "8",
    questionnaireCopy: "#3A3428", questionnaireLabel: "#AF8540",
    inputSurface: "#FFFFFF", inputText: "#211C16", optionSurface: "#FFFFFF", optionText: "#726A5B",
    selectedSurface: "#C9A15A", selectedText: "#14141C", progress: "#C9A15A",
    pageTitleSize: "62", bodyTextSize: "16", questionTextSize: "17", optionTextSize: "11",
    fieldLabelSize: "12", fieldTextSize: "14", metaTextSize: "12",
    visualTitleSize: "72", visualBodySize: "22", contentMaxWidth: "720", intakeMaxWidth: "840",
    questionMaxWidth: "880", contentGutter: "72",
    logoUrl: legacyLogoUrl, emailLogoUrl: legacyLogoUrl,
    reportLogoUrl: legacyLogoUrl, faviconUrl: "/icon-192.png", bannerUrl: "",
  },
  stages: stageContent,
  tracks: {},
};

const BrandContext = React.createContext(defaults);

function normalisePublicLogoUrl(value) {
  return !value || value === transparentLogoUrl || String(value).startsWith("/media-uploads/") ? legacyLogoUrl : value;
}

function applyBranding(branding) {
  const root = document.documentElement;
  const tokens = {
    "--paper": branding.canvas,
    "--cream": branding.surface,
    "--ink": branding.textPrimary,
    "--muted": branding.textMuted,
    "--line": branding.border,
    "--gold": branding.cta,
    "--gold-dark": branding.ctaHover,
    "--orange": branding.heart,
    "--head": branding.head,
    "--navy": branding.navy,
    "--heading-font": branding.headingFont,
    "--body-font": branding.bodyFont,
    "--base-font-size": `${Number(branding.baseFontSize || 16)}px`,
    "--questionnaire-copy": branding.questionnaireCopy,
    "--questionnaire-label": branding.questionnaireLabel,
    "--questionnaire-input-surface": branding.inputSurface,
    "--questionnaire-input-text": branding.inputText,
    "--questionnaire-option-surface": branding.optionSurface,
    "--questionnaire-option-text": branding.optionText,
    "--questionnaire-selected-surface": branding.selectedSurface,
    "--questionnaire-selected-text": branding.selectedText,
    "--questionnaire-progress": branding.progress,
    "--questionnaire-title-size": `${Number(branding.pageTitleSize || 62)}px`,
    "--questionnaire-body-size": `${Number(branding.bodyTextSize || 16)}px`,
    "--questionnaire-question-size": `${Number(branding.questionTextSize || 17)}px`,
    "--questionnaire-option-size": `${Number(branding.optionTextSize || 11)}px`,
    "--questionnaire-label-size": `${Number(branding.fieldLabelSize || 12)}px`,
    "--questionnaire-field-size": `${Number(branding.fieldTextSize || 14)}px`,
    "--questionnaire-meta-size": `${Number(branding.metaTextSize || 12)}px`,
    "--questionnaire-visual-title-size": `${Number(branding.visualTitleSize || 72)}px`,
    "--questionnaire-visual-body-size": `${Number(branding.visualBodySize || 22)}px`,
    "--questionnaire-content-width": `${Number(branding.contentMaxWidth || 720)}px`,
    "--questionnaire-intake-width": `${Number(branding.intakeMaxWidth || 840)}px`,
    "--questionnaire-question-width": `${Number(branding.questionMaxWidth || 880)}px`,
    "--questionnaire-gutter": `${Number(branding.contentGutter || 72)}px`,
    "--card-radius": `${Number(branding.cardRadius || 8)}px`,
    "--button-radius": `${Number(branding.buttonRadius || 8)}px`,
  };
  Object.entries(tokens).forEach(([key, value]) => value && root.style.setProperty(key, value));
  const favicon = document.querySelector('link[rel="icon"]');
  if (favicon && branding.faviconUrl) favicon.href = branding.faviconUrl;
}

export function BrandProvider({ children }) {
  const [configuration, setConfiguration] = React.useState(defaults);
  const [loaded, setLoaded] = React.useState(false);

  React.useEffect(() => {
    let active = true;
    api.publicConfiguration()
      .then(remote => {
        if (!active) return;
        const remoteBranding = remote.branding || {};
        const nextBranding = {
          ...defaults.branding,
          ...remoteBranding,
          logoUrl: normalisePublicLogoUrl(remoteBranding.logoUrl),
        };
        const next = {
          branding: nextBranding,
          stages: { ...defaults.stages, ...(remote.stages || {}) },
          tracks: remote.tracks || {},
        };
        applyBranding(next.branding);
        setConfiguration(next);
      })
      .catch(() => applyBranding(defaults.branding))
      .finally(() => active && setLoaded(true));
    return () => { active = false; };
  }, []);

  return <BrandContext.Provider value={{ ...configuration, loaded }}>{children}</BrandContext.Provider>;
}

export function useBranding() {
  return React.useContext(BrandContext);
}

export function BrandLogo({ className = "", alt = "Atom Global Consulting" }) {
  const { branding } = useBranding();
  return <img className={className} src={normalisePublicLogoUrl(branding.logoUrl)} alt={alt} />;
}

export { defaults as defaultConfiguration, transparentLogoUrl };
