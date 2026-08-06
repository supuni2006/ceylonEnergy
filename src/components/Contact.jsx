import { useState } from "react";

// This form has no PHP backend anymore. It's wired for EmailJS
// (https://www.emailjs.com — free tier available, sends straight from
// the browser, no server needed). Fill in the three values below from
// your EmailJS dashboard to go live. Until then, submitting falls back
// to opening the visitor's email client with the message pre-filled.
const EMAILJS_SERVICE_ID = "";
const EMAILJS_TEMPLATE_ID = "";
const EMAILJS_PUBLIC_KEY = "";

const CONTACT_EMAIL = "info@ceylonenergyservices.com";

export default function Contact() {
  const [status, setStatus] = useState({ state: "idle", message: "" });
  const [form, setForm] = useState({
    name: "",
    email: "",
    subject: "",
    tel: "",
    message: "",
  });

  function handleChange(e) {
    const { name, value } = e.target;
    setForm((f) => ({ ...f, [name]: value }));
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setStatus({ state: "sending", message: "Sending..." });

    const configured = EMAILJS_SERVICE_ID && EMAILJS_TEMPLATE_ID && EMAILJS_PUBLIC_KEY;

    if (!configured) {
      // Fallback: open a pre-filled mailto: link so the message still gets sent.
      const body = `Name: ${form.name}\nEmail: ${form.email}\nPhone: ${form.tel}\n\n${form.message}`;
      const mailto = `mailto:${CONTACT_EMAIL}?subject=${encodeURIComponent(
        form.subject || "Website enquiry"
      )}&body=${encodeURIComponent(body)}`;
      window.location.href = mailto;
      setStatus({
        state: "ok",
        message: "Opening your email client — please hit send there to finish.",
      });
      return;
    }

    try {
      const res = await fetch("https://api.emailjs.com/api/v1.0/email/send", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          service_id: EMAILJS_SERVICE_ID,
          template_id: EMAILJS_TEMPLATE_ID,
          user_id: EMAILJS_PUBLIC_KEY,
          template_params: form,
        }),
      });
      if (!res.ok) throw new Error("send failed");
      setStatus({ state: "ok", message: "Thank you — we'll be in touch shortly." });
      setForm({ name: "", email: "", subject: "", tel: "", message: "" });
    } catch {
      setStatus({
        state: "err",
        message: `Couldn't send automatically — please email ${CONTACT_EMAIL} directly.`,
      });
    }
  }

  return (
    <section id="contact" className="wrap-dark pad">
      <div className="container contact-grid">
        <div>
          <p className="eyebrow gold">Get In Touch</p>
          <h2 className="section-title reveal">Let&apos;s plan your build</h2>
          <p className="reveal">
            Tell us what you&apos;re working on — solar, security, structural, or
            all three — and we&apos;ll come back with a scoped plan.
          </p>
          <ul className="contact-info-list reveal">
            <li>
              <i className="budicon-pin"></i>
              <p>
                World Trade Center, Level 26 &amp; 34,
                <br />
                East Tower, Echelon Square, Colombo 00100
              </p>
            </li>
            <li>
              <i className="budicon-telephone"></i>
              <p>
                <a href="tel:0771609688">0771 60 96 88</a> &nbsp;·&nbsp;{" "}
                <a href="tel:0113654565">0113 65 45 65</a>
              </p>
            </li>
            <li>
              <i className="budicon-mail"></i>
              <p>
                <a href={`mailto:${CONTACT_EMAIL}`}>{CONTACT_EMAIL}</a>
              </p>
            </li>
          </ul>
        </div>
        <div className="reveal">
          <form className="vanilla vanilla-form" id="contactForm" noValidate onSubmit={handleSubmit}>
            <div className="field-row">
              <div className="field">
                <label htmlFor="f-name">Your name*</label>
                <input
                  id="f-name"
                  type="text"
                  name="name"
                  required
                  value={form.name}
                  onChange={handleChange}
                />
              </div>
              <div className="field">
                <label htmlFor="f-email">Your e-mail*</label>
                <input
                  id="f-email"
                  type="email"
                  name="email"
                  required
                  value={form.email}
                  onChange={handleChange}
                />
              </div>
            </div>
            <div className="field-row">
              <div className="field">
                <label htmlFor="f-subject">Subject</label>
                <input
                  id="f-subject"
                  type="text"
                  name="subject"
                  value={form.subject}
                  onChange={handleChange}
                />
              </div>
              <div className="field">
                <label htmlFor="f-tel">Your phone</label>
                <input
                  id="f-tel"
                  type="tel"
                  name="tel"
                  value={form.tel}
                  onChange={handleChange}
                />
              </div>
            </div>
            <div className="field">
              <label htmlFor="f-message">Your message*</label>
              <textarea
                id="f-message"
                name="message"
                required
                value={form.message}
                onChange={handleChange}
              ></textarea>
            </div>
            <button
              type="submit"
              className="btn"
              id="submitBtn"
              disabled={status.state === "sending"}
            >
              <span>Send Message</span>
            </button>
            <div className={`form-note${status.state === "ok" ? " ok" : status.state === "err" ? " err" : ""}`}>
              {status.message}
            </div>
          </form>
        </div>
      </div>
    </section>
  );
}
