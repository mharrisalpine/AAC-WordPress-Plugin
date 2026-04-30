import React, { useEffect, useMemo, useState } from 'react';
import { Helmet } from 'react-helmet';
import { motion } from 'framer-motion';
import { ArrowUpRight, CheckCircle2, ClipboardList, FileText, Mountain, Send } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToast } from '@/components/ui/use-toast';
import { useAuth } from '@/hooks/useAuth';
import { submitGrantApplication } from '@/lib/memberApi';
import {
  buildGrantFormState,
  formatGrantApplicationDate,
  getGrantFormFields,
  getGrantOpportunities,
  getGrantOpportunityBySlug,
  grantPortalSourceUrl,
  grantStatusClassName,
  normalizeGrantApplications,
} from '@/lib/grants';
import { getFullName } from '@/lib/memberProfile';
import { getPortalUiSettings } from '@/lib/portalSettings';
import { cn } from '@/lib/utils';

const GRANT_FIELD_COLUMN_TYPES = new Set(['text', 'email', 'number', 'select']);

const GrantApplicationPage = () => {
  const { profile, refreshProfile } = useAuth();
  const { toast } = useToast();
  const portalContent = getPortalUiSettings().content;
  const grantOpportunities = useMemo(() => getGrantOpportunities(), []);
  const grantFormFields = useMemo(() => getGrantFormFields(), []);
  const applications = useMemo(
    () => normalizeGrantApplications(profile?.grant_applications),
    [profile?.grant_applications],
  );
  const [selectedGrantSlug, setSelectedGrantSlug] = useState(grantOpportunities[0]?.slug || '');
  const [submitting, setSubmitting] = useState(false);
  const [form, setForm] = useState(() => buildGrantFormState(grantFormFields));

  useEffect(() => {
    setForm(buildGrantFormState(grantFormFields));
  }, [grantFormFields]);

  useEffect(() => {
    if (!grantOpportunities.length) {
      setSelectedGrantSlug('');
      return;
    }

    if (!grantOpportunities.some((opportunity) => opportunity.slug === selectedGrantSlug)) {
      setSelectedGrantSlug(grantOpportunities[0].slug);
    }
  }, [grantOpportunities, selectedGrantSlug]);

  const selectedOpportunity = useMemo(
    () => getGrantOpportunityBySlug(selectedGrantSlug, grantOpportunities),
    [selectedGrantSlug, grantOpportunities],
  );

  const applicantName = getFullName(profile?.account_info);
  const applicantEmail = profile?.account_info?.email || '';
  const applicantPhone = profile?.account_info?.phone || 'Add your phone number in Account Settings to strengthen your application profile.';

  const handleFieldChange = (fieldKey, value) => {
    setForm((current) => ({
      ...current,
      [fieldKey]: value,
    }));
  };

  const handleSubmit = async (event) => {
    event.preventDefault();

    if (!selectedOpportunity?.slug) {
      toast({
        variant: 'destructive',
        title: 'Select a grant',
        description: 'Choose an AAC grant opportunity before submitting your application.',
      });
      return;
    }

    const missingField = grantFormFields.find((field) => field.required && !String(form[field.field_key] || '').trim());
    if (missingField) {
      toast({
        variant: 'destructive',
        title: 'Complete the required fields',
        description: `${missingField.label} is required before the application can be submitted.`,
      });
      return;
    }

    const submissionFields = grantFormFields.map((field) => ({
      field_key: field.field_key,
      label: field.label,
      type: field.type,
      value: String(form[field.field_key] || ''),
    }));

    setSubmitting(true);
    try {
      await submitGrantApplication({
        grant_slug: selectedOpportunity.slug,
        grant_name: selectedOpportunity.name,
        category: selectedOpportunity.category,
        fields: submissionFields,
      });
      await refreshProfile();

      toast({
        title: 'Grant application submitted',
        description: `${selectedOpportunity.name} is now in the review workflow and will show live status updates in My Grants.`,
      });

      setForm(buildGrantFormState(grantFormFields));
    } catch (error) {
      toast({
        variant: 'destructive',
        title: 'Unable to submit application',
        description: error.message || 'Please try again in a moment.',
      });
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <>
      <Helmet>
        <title>AAC Grants - American Alpine Club</title>
        <meta
          name="description"
          content="Review active AAC grant opportunities and submit a member application from the portal."
        />
      </Helmet>

      <div className="space-y-6 py-6">
        <motion.section
          initial={{ opacity: 0, y: 18 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.45 }}
          className="overflow-hidden rounded-[32px] border border-black/10 bg-[#030000] text-white shadow-[0_24px_70px_rgba(3,0,0,0.18)]"
        >
          <div className="grid gap-8 px-6 py-8 md:px-8 lg:grid-cols-[1.1fr,0.9fr] lg:items-end">
            <div>
              <div className="inline-flex items-center gap-2 rounded-full border border-[#f8c235]/35 bg-[#f8c235]/10 px-4 py-2 text-[0.72rem] font-semibold uppercase tracking-[0.22em] text-[#f8c235]">
                <Mountain className="h-4 w-4" />
                {portalContent.grants_page_kicker || 'AAC Grants'}
              </div>
              <h1 className="mt-4 max-w-3xl text-4xl font-bold leading-tight md:text-5xl">
                {portalContent.grants_page_title || 'Support ambitious climbing, research, and community projects.'}
              </h1>
              <p className="mt-4 max-w-3xl text-base leading-7 text-white/75">
                {portalContent.grants_page_description || 'Review current AAC grant opportunities, choose the best fit, and submit your application from inside the member portal.'}
              </p>
              <div className="mt-6 flex flex-wrap gap-3">
                <Button
                  asChild
                  className="bg-[#f8c235] text-black hover:bg-[#ddb01d]"
                >
                  <a href={grantPortalSourceUrl} target="_blank" rel="noreferrer">
                    Open Full Submittable
                    <ArrowUpRight className="ml-2 h-4 w-4" />
                  </a>
                </Button>
                <div className="rounded-full border border-white/10 bg-white/[0.04] px-4 py-3 text-sm text-white/80">
                  Current opportunities: {grantOpportunities.length}
                </div>
              </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-1 xl:grid-cols-3">
              <div className="rounded-[24px] border border-white/10 bg-white/[0.05] p-5">
                <p className="text-xs uppercase tracking-[0.2em] text-[#f8c235]">Applicant</p>
                <p className="mt-2 text-lg font-semibold">{applicantName}</p>
              </div>
              <div className="rounded-[24px] border border-white/10 bg-white/[0.05] p-5">
                <p className="text-xs uppercase tracking-[0.2em] text-[#f8c235]">Email</p>
                <p className="mt-2 text-sm text-white/80">{applicantEmail || 'No email on file'}</p>
              </div>
              <div className="rounded-[24px] border border-white/10 bg-white/[0.05] p-5">
                <p className="text-xs uppercase tracking-[0.2em] text-[#f8c235]">Phone</p>
                <p className="mt-2 text-sm text-white/80">{applicantPhone}</p>
              </div>
            </div>
          </div>
        </motion.section>

        <div className="grid gap-6 xl:grid-cols-[0.9fr,1.1fr]">
          <motion.section
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45, delay: 0.05 }}
            className="card-gradient rounded-[28px] border border-stone-200/80 p-6"
          >
            <div className="mb-5 flex items-start gap-3">
              <div className="rounded-2xl bg-[#c8a43a]/18 p-3 text-[#6b5310]">
                <ClipboardList className="h-5 w-5" />
              </div>
              <div>
                <h2 className="text-xl font-bold text-stone-900">Current Opportunities</h2>
                <p className="mt-1 text-sm text-stone-600">
                  Choose the grant that best matches your project before you submit.
                </p>
              </div>
            </div>

            <div className="space-y-3">
              {grantOpportunities.map((opportunity) => {
                const active = opportunity.slug === selectedGrantSlug;

                return (
                  <button
                    key={opportunity.slug}
                    type="button"
                    onClick={() => setSelectedGrantSlug(opportunity.slug)}
                    className={cn(
                      'w-full rounded-[22px] border p-4 text-left transition-all',
                      active
                        ? 'border-[#c8a43a] bg-[#fff8ea] shadow-[0_16px_32px_rgba(200,164,58,0.16)]'
                        : 'border-stone-200 bg-white hover:border-[#c8a43a]/50 hover:bg-[#fffbf4]',
                    )}
                  >
                    <div className="flex items-start justify-between gap-4">
                      <div>
                        <p className="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-[#8a6a19]">
                          {opportunity.category}
                        </p>
                        <h3 className="mt-2 text-lg font-semibold text-stone-900">{opportunity.name}</h3>
                      </div>
                      <span className="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700">
                        {opportunity.award}
                      </span>
                    </div>
                    <p className="mt-3 text-sm leading-6 text-stone-700">{opportunity.fit}</p>
                  </button>
                );
              })}
            </div>
          </motion.section>

          <motion.section
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45, delay: 0.08 }}
            className="card-gradient rounded-[28px] border border-stone-200/80 p-6"
          >
            <div className="mb-6 flex items-start gap-3">
              <div className="rounded-2xl bg-[#b71c1c]/10 p-3 text-[#8f1515]">
                <FileText className="h-5 w-5" />
              </div>
              <div>
                <h2 className="text-xl font-bold text-stone-900">{selectedOpportunity.name || 'Grant Application'}</h2>
                <p className="mt-1 text-sm text-stone-600">{selectedOpportunity.summary || 'Choose an opportunity to view its description and apply.'}</p>
              </div>
            </div>

            {selectedOpportunity.highlights?.length ? (
              <div className="grid gap-4 rounded-[24px] border border-stone-200 bg-stone-50/90 p-5 md:grid-cols-3">
                {selectedOpportunity.highlights.map((highlight) => (
                  <div key={highlight} className="rounded-[20px] bg-white px-4 py-4 text-sm leading-6 text-stone-700">
                    {highlight}
                  </div>
                ))}
              </div>
            ) : null}

            <form onSubmit={handleSubmit} className="mt-6 space-y-5">
              <div className="grid gap-4 md:grid-cols-2">
                {grantFormFields.map((field) => {
                  const inputId = `grant-${field.field_key}`;
                  const value = form[field.field_key] || '';
                  const isWide = !GRANT_FIELD_COLUMN_TYPES.has(field.type);

                  return (
                    <div key={field.field_key} className={isWide ? 'md:col-span-2' : ''}>
                      <Label htmlFor={inputId} className="text-black">
                        {field.label}
                        {field.required ? <span className="ml-1 text-[#8f1515]">*</span> : null}
                      </Label>
                      {field.type === 'textarea' ? (
                        <textarea
                          id={inputId}
                          value={value}
                          onChange={(event) => handleFieldChange(field.field_key, event.target.value)}
                          rows={7}
                          className="mt-1 flex w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-sm text-black ring-offset-background placeholder:text-stone-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#c8a43a] focus-visible:ring-offset-2"
                          placeholder={field.placeholder}
                        />
                      ) : field.type === 'select' ? (
                        <select
                          id={inputId}
                          value={value}
                          onChange={(event) => handleFieldChange(field.field_key, event.target.value)}
                          className="mt-1 flex h-10 w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-sm text-black ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#c8a43a] focus-visible:ring-offset-2"
                        >
                          <option value="">Select an option</option>
                          {field.options.map((option) => (
                            <option key={option} value={option}>
                              {option}
                            </option>
                          ))}
                        </select>
                      ) : (
                        <Input
                          id={inputId}
                          type={field.type === 'number' ? 'text' : field.type}
                          value={value}
                          onChange={(event) => handleFieldChange(field.field_key, event.target.value)}
                          className="mt-1 bg-white border-stone-300 text-black"
                          placeholder={field.placeholder}
                        />
                      )}
                      {field.help_text ? (
                        <p className="mt-2 text-sm leading-6 text-stone-500">{field.help_text}</p>
                      ) : null}
                    </div>
                  );
                })}
              </div>

              <div className="flex flex-wrap items-center justify-between gap-3 rounded-[24px] border border-stone-200 bg-stone-50/80 px-5 py-4">
                <p className="text-sm leading-6 text-stone-700">
                  Submitted applications now feed the AAC review workflow directly and the live reviewer status appears in your member profile.
                </p>
                <Button
                  type="submit"
                  disabled={submitting}
                  className="bg-[#b71c1c] text-white hover:bg-[#8f1515]"
                >
                  <Send className="mr-2 h-4 w-4" />
                  {submitting ? 'Submitting...' : 'Submit Application'}
                </Button>
              </div>
            </form>
          </motion.section>
        </div>

        <motion.section
          initial={{ opacity: 0, y: 18 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.45, delay: 0.12 }}
          className="card-gradient rounded-[28px] border border-stone-200/80 p-6"
        >
          <div className="mb-5 flex items-start gap-3">
            <div className="rounded-2xl bg-[#c8a43a]/18 p-3 text-[#6b5310]">
              <CheckCircle2 className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-xl font-bold text-stone-900">My Grant Applications</h2>
              <p className="mt-1 text-sm text-stone-600">Track what you have already submitted through the member portal.</p>
            </div>
          </div>

          {applications.length ? (
            <div className="space-y-3">
              {applications.map((application) => (
                <div
                  key={application.id}
                  className="flex flex-col gap-4 rounded-[22px] border border-stone-200 bg-white px-5 py-4 md:flex-row md:items-center md:justify-between"
                >
                  <div className="space-y-1">
                    <p className="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-[#8a6a19]">
                      {application.category}
                    </p>
                    <h3 className="text-lg font-semibold text-stone-900">{application.grant_name}</h3>
                    <p className="text-sm text-stone-600">
                      {application.project_title || 'Application submitted'} • {formatGrantApplicationDate(application.application_date)}
                    </p>
                  </div>
                  <span
                    className={cn(
                      'inline-flex items-center rounded-full px-3 py-1.5 text-sm font-semibold',
                      grantStatusClassName(application.status),
                    )}
                  >
                    {application.status}
                  </span>
                </div>
              ))}
            </div>
          ) : (
            <div className="rounded-[24px] border border-dashed border-stone-300 bg-stone-50/80 px-6 py-8 text-center text-stone-600">
              No grant applications yet. Choose an opportunity above to start your first AAC submission.
            </div>
          )}
        </motion.section>
      </div>
    </>
  );
};

export default GrantApplicationPage;
