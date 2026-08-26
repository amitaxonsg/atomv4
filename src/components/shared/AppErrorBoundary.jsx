import React from "react";

export default class AppErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { error: null, errorInfo: null };
  }

  static getDerivedStateFromError(error) {
    return { error };
  }

  componentDidCatch(error, errorInfo) {
    this.setState({ errorInfo });
    if (import.meta.env.DEV) console.error("Application render failed", error, errorInfo);
  }

  render() {
    const { error, errorInfo } = this.state;
    if (!error) return this.props.children;

    return <main className="fatal-error" role="alert">
      <section className="fatal-error__card">
        <img src="/media/brand/atom-global-wordmark.png?v=20260719-direct-1" alt="Atom Global Consulting" />
        <p className="eyebrow">Growth Alignment</p>
        <h1>We could not load the assessment.</h1>
        <p>Please reload the page. If the problem continues, contact Atom Global support and tell us what you were trying to do.</p>
        <button className="button button--primary" type="button" onClick={() => window.location.reload()}>Reload</button>
        {import.meta.env.DEV && <details className="fatal-error__details">
          <summary>Technical error details</summary>
          <pre>{error.stack || error.message}{errorInfo?.componentStack || ""}</pre>
        </details>}
      </section>
    </main>;
  }
}
