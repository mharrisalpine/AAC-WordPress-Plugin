import React, { useEffect, useMemo, useState } from 'react';
import { motion } from 'framer-motion';
import { CheckCircle2, ClipboardList, Search, Shield, UserCheck, XCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToast } from '@/components/ui/use-toast';
import {
  assignGrantApprovalReviewer,
  getGrantApprovalApplication,
  getGrantApprovalQueue,
  updateGrantApprovalWorkflow,
} from '@/lib/memberApi';
import { cn } from '@/lib/utils';

const formatDateTime = (value, fallback = 'Not yet') => {
  if (!value) {
    return fallback;
  }

  const parsed = new Date(value);
  return Number.isNaN(parsed.getTime()) ? fallback : parsed.toLocaleString();
};

const SummaryCard = ({ title, value, description }) => (
  <div className="border border-white/10 bg-[#111214] px-5 py-4">
    <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-[#f8c235]">{title}</p>
    <p className="mt-3 text-3xl font-black text-white">{value}</p>
    <p className="mt-2 text-sm leading-6 text-stone-300">{description}</p>
  </div>
);

const GrantApprovalsPage = () => {
  const { toast } = useToast();
  const [queue, setQueue] = useState([]);
  const [counts, setCounts] = useState({});
  const [labels, setLabels] = useState({});
  const [reviewers, setReviewers] = useState([]);
  const [statusFilter, setStatusFilter] = useState('');
  const [searchInput, setSearchInput] = useState('');
  const [appliedSearch, setAppliedSearch] = useState('');
  const [selectedApplicationId, setSelectedApplicationId] = useState(null);
  const [selectedApplication, setSelectedApplication] = useState(null);
  const [transitions, setTransitions] = useState([]);
  const [assignedReviewerId, setAssignedReviewerId] = useState(0);
  const [reviewerNote, setReviewerNote] = useState('');
  const [loadingQueue, setLoadingQueue] = useState(true);
  const [loadingDetail, setLoadingDetail] = useState(false);
  const [savingAction, setSavingAction] = useState(false);
  const [pageError, setPageError] = useState('');

  const loadQueue = async (preserveSelection = true) => {
    setLoadingQueue(true);
    setPageError('');

    try {
      const response = await getGrantApprovalQueue({
        status: statusFilter,
        search: appliedSearch,
      });

      const nextQueue = Array.isArray(response?.applications) ? response.applications : [];
      setQueue(nextQueue);
      setCounts(response?.counts || {});
      setLabels(response?.labels || {});
      setReviewers(Array.isArray(response?.reviewers) ? response.reviewers : []);

      if (!preserveSelection) {
        setSelectedApplicationId(null);
        setSelectedApplication(null);
        return;
      }

      if (selectedApplicationId && nextQueue.some((application) => application.id === selectedApplicationId)) {
        return;
      }

      if (nextQueue.length) {
        setSelectedApplicationId(nextQueue[0].id);
      } else {
        setSelectedApplicationId(null);
        setSelectedApplication(null);
      }
    } catch (error) {
      setPageError(error?.message || 'We could not load the grant approvals queue right now.');
      setQueue([]);
      setSelectedApplication(null);
    } finally {
      setLoadingQueue(false);
    }
  };

  const loadApplication = async (applicationId) => {
    if (!applicationId) {
      setSelectedApplication(null);
      return;
    }

    setLoadingDetail(true);

    try {
      const response = await getGrantApprovalApplication(applicationId);
      const nextApplication = response?.application || null;
      setSelectedApplication(nextApplication);
      setTransitions(Array.isArray(response?.transitions) ? response.transitions : []);
      setReviewers(Array.isArray(response?.reviewers) && response.reviewers.length ? response.reviewers : reviewers);
      setAssignedReviewerId(Number(nextApplication?.assigned_reviewer_id || 0));
      setReviewerNote('');
    } catch (error) {
      toast({
        title: 'Grant review unavailable',
        description: error?.message || 'We could not load that application.',
        variant: 'destructive',
      });
    } finally {
      setLoadingDetail(false);
    }
  };

  useEffect(() => {
    void loadQueue();
  }, [statusFilter, appliedSearch]);

  useEffect(() => {
    if (selectedApplicationId) {
      void loadApplication(selectedApplicationId);
    }
  }, [selectedApplicationId]);

  const statusPills = useMemo(() => {
    const entries = Object.entries(labels || {});
    return [
      { key: '', label: 'All', count: Object.values(counts || {}).reduce((sum, value) => sum + Number(value || 0), 0) },
      ...entries.map(([key, label]) => ({
        key,
        label,
        count: Number(counts?.[key] || 0),
      })),
    ];
  }, [counts, labels]);

  const summaryValues = useMemo(() => {
    const total = Object.values(counts || {}).reduce((sum, value) => sum + Number(value || 0), 0);
    const activeReview = Number(counts?.eligibility_review || 0) + Number(counts?.committee_review || 0);
    const needsRevision = Number(counts?.needs_revision || 0);
    const approved = Number(counts?.approved || 0);
    const unassigned = queue.filter((application) => !application.assigned_reviewer_id).length;

    return { total, activeReview, needsRevision, approved, unassigned };
  }, [counts, queue]);

  const handleSearchSubmit = (event) => {
    event.preventDefault();
    setAppliedSearch(searchInput.trim());
  };

  const handleReviewerSave = async () => {
    if (!selectedApplication?.id) {
      return;
    }

    setSavingAction(true);
    try {
      const response = await assignGrantApprovalReviewer(selectedApplication.id, assignedReviewerId);
      setSelectedApplication(response?.application || selectedApplication);
      await loadQueue();
      toast({
        title: 'Reviewer updated',
        description: 'The assigned reviewer was saved.',
      });
    } catch (error) {
      toast({
        title: 'Reviewer update failed',
        description: error?.message || 'We could not save the assigned reviewer.',
        variant: 'destructive',
      });
    } finally {
      setSavingAction(false);
    }
  };

  const handleWorkflowAction = async (nextStatus, directTerminalDecision = false) => {
    if (!selectedApplication?.id) {
      return;
    }

    setSavingAction(true);
    try {
      const response = await updateGrantApprovalWorkflow(selectedApplication.id, {
        next_status: nextStatus,
        note: reviewerNote,
        assigned_reviewer_id: assignedReviewerId,
        direct_terminal_decision: directTerminalDecision,
      });

      setSelectedApplication(response?.application || selectedApplication);
      setTransitions(Array.isArray(response?.transitions) ? response.transitions : []);
      setReviewerNote('');
      await loadQueue();
      toast({
        title: 'Application updated',
        description: 'The grant application workflow was updated.',
      });
    } catch (error) {
      toast({
        title: 'Workflow update failed',
        description: error?.message || 'We could not move the application to the next step.',
        variant: 'destructive',
      });
    } finally {
      setSavingAction(false);
    }
  };

  return (
    <div className="py-6">
      <motion.div
        initial={{ opacity: 0, y: 18 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.45 }}
        className="space-y-6"
      >
        <section className="border border-white/10 bg-[linear-gradient(180deg,#0d0d0f_0%,#111214_55%,#17181b_100%)] px-6 py-7 text-white">
          <div className="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div className="max-w-3xl">
              <p className="text-[0.72rem] font-semibold uppercase tracking-[0.22em] text-[#f8c235]">Volunteer Reviewer Portal</p>
              <h1 className="mt-3 text-3xl font-black tracking-tight text-white md:text-5xl">Grant Approvals</h1>
              <p className="mt-4 text-sm leading-7 text-stone-300 md:text-base">
                Review incoming grant submissions, assign volunteer reviewers, move applications through the workflow,
                and make final decisions without dropping back into wp-admin.
              </p>
            </div>
            <div className="flex flex-wrap gap-3">
              <Button
                type="button"
                className="border border-[#8f1515] bg-[#8f1515] text-white hover:bg-[#761111]"
                onClick={() => window.location.assign('/grant-review/')}
              >
                Open WordPress page
              </Button>
            </div>
          </div>

          <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <SummaryCard title="Applications in queue" value={summaryValues.total} description="All captured grant submissions in the workflow." />
            <SummaryCard title="Active review" value={summaryValues.activeReview} description="Applications currently in eligibility or committee review." />
            <SummaryCard title="Needs revision" value={summaryValues.needsRevision} description="Applications waiting on follow-up or missing details." />
            <SummaryCard title="Unassigned" value={summaryValues.unassigned} description="Applications in the current queue without a reviewer." />
            <SummaryCard title="Approved" value={summaryValues.approved} description="Applications that have already reached a final approved decision." />
          </div>
        </section>

        <section className="border border-stone-200 bg-white px-6 py-6">
          <div className="flex flex-col gap-4">
            <div className="flex flex-wrap gap-3">
              {statusPills.map((pill) => (
                <button
                  key={pill.key || 'all'}
                  type="button"
                  className={cn(
                    'inline-flex items-center gap-3 border px-4 py-2 text-sm font-semibold transition',
                    statusFilter === pill.key
                      ? 'border-[#8f1515] bg-[#8f1515] text-white'
                      : 'border-stone-300 bg-white text-stone-800 hover:border-stone-500'
                  )}
                  onClick={() => setStatusFilter(pill.key)}
                >
                  <span>{pill.label}</span>
                  <span className={cn(
                    'inline-flex min-w-[1.75rem] items-center justify-center px-2 py-0.5 text-xs',
                    statusFilter === pill.key ? 'bg-white/15 text-white' : 'bg-stone-100 text-stone-700'
                  )}>
                    {pill.count}
                  </span>
                </button>
              ))}
            </div>

            <form className="flex flex-col gap-3 md:flex-row" onSubmit={handleSearchSubmit}>
              <div className="relative flex-1">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" />
                <Input
                  value={searchInput}
                  onChange={(event) => setSearchInput(event.target.value)}
                  placeholder="Search applicant, grant, or project title"
                  className="pl-10"
                />
              </div>
              <Button type="submit" variant="outline" className="border-stone-300 text-black hover:bg-stone-100">
                Search
              </Button>
            </form>

            {pageError ? (
              <div className="border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                {pageError}
              </div>
            ) : null}
          </div>
        </section>

        <div className="grid gap-6 xl:grid-cols-[0.95fr,1.05fr]">
          <section className="border border-stone-200 bg-white px-6 py-6">
            <div className="mb-4 flex items-center justify-between gap-4">
              <div>
                <h2 className="text-xl font-bold text-stone-900">Review Queue</h2>
                <p className="mt-1 text-sm text-stone-600">Choose an application to open the full reviewer workspace.</p>
              </div>
              {loadingQueue ? <span className="text-sm text-stone-500">Loading…</span> : null}
            </div>

            <div className="space-y-4">
              {!loadingQueue && !queue.length ? (
                <div className="border border-dashed border-stone-300 bg-stone-50 px-5 py-8 text-center text-sm text-stone-600">
                  No applications matched this filter.
                </div>
              ) : null}

              {queue.map((application) => (
                <button
                  key={application.id}
                  type="button"
                  onClick={() => setSelectedApplicationId(application.id)}
                  className={cn(
                    'block w-full border px-5 py-5 text-left transition',
                    selectedApplicationId === application.id
                      ? 'border-[#8f1515] bg-[#fff7f5]'
                      : 'border-stone-200 bg-white hover:border-stone-400 hover:bg-stone-50'
                  )}
                >
                  <div className="flex flex-col gap-3">
                    <div className="flex items-start justify-between gap-3">
                      <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-[#8a6a19]">
                        {application.grant_name || 'Grant application'}
                      </p>
                      <span className="bg-stone-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.12em] text-stone-700">
                        {application.workflow_status_label}
                      </span>
                    </div>
                    <div>
                      <h3 className="text-lg font-bold text-stone-900">
                        {application.project_title || application.grant_name || 'Untitled application'}
                      </h3>
                      <p className="mt-1 text-sm font-medium text-stone-800">
                        {application.applicant_name || application.applicant_email || 'Unknown applicant'}
                      </p>
                      <p className="mt-1 text-sm text-stone-600">{application.applicant_email || 'No applicant email mapped yet'}</p>
                    </div>
                    <div className="grid gap-3 text-sm text-stone-700 md:grid-cols-2">
                      <div>
                        <p className="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-stone-500">Submitted</p>
                        <p className="mt-1">{formatDateTime(application.submitted_at, 'Not recorded')}</p>
                      </div>
                      <div>
                        <p className="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-stone-500">Reviewer</p>
                        <p className="mt-1">{application.assigned_reviewer_name || 'Unassigned'}</p>
                      </div>
                    </div>
                  </div>
                </button>
              ))}
            </div>
          </section>

          <section className="border border-white/10 bg-[#111214] px-6 py-6 text-white">
            <div className="mb-5 flex items-center justify-between gap-4">
              <div>
                <h2 className="text-xl font-bold text-white">Application Review</h2>
                <p className="mt-1 text-sm text-stone-300">Reviewer assignment, workflow steps, notes, and submitted fields all live here.</p>
              </div>
              {loadingDetail ? <span className="text-sm text-stone-400">Loading…</span> : null}
            </div>

            {!selectedApplication ? (
              <div className="border border-dashed border-white/10 bg-[#18181b] px-5 py-10 text-center text-sm text-stone-300">
                Select a grant application from the queue to open it here.
              </div>
            ) : (
              <div className="space-y-6">
                <div className="flex flex-col gap-4 border border-white/10 bg-[#18181b] px-5 py-5">
                  <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                      <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-[#f8c235]">
                        {selectedApplication.grant_name || 'Grant application'}
                      </p>
                      <h3 className="mt-2 text-2xl font-black text-white">
                        {selectedApplication.project_title || selectedApplication.grant_name || 'Untitled application'}
                      </h3>
                      <p className="mt-2 text-sm text-stone-300">
                        {selectedApplication.applicant_name || selectedApplication.applicant_email}
                      </p>
                    </div>
                    <span className="self-start bg-[#8f1515] px-3 py-2 text-xs font-semibold uppercase tracking-[0.14em] text-white">
                      {selectedApplication.workflow_status_label}
                    </span>
                  </div>

                  <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <div className="border border-white/10 bg-[#111214] px-4 py-4">
                      <p className="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-stone-400">Applicant</p>
                      <p className="mt-2 text-sm text-white">{selectedApplication.applicant_name || 'Not mapped yet'}</p>
                    </div>
                    <div className="border border-white/10 bg-[#111214] px-4 py-4">
                      <p className="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-stone-400">Email</p>
                      <p className="mt-2 text-sm text-white break-all">{selectedApplication.applicant_email || 'Not mapped yet'}</p>
                    </div>
                    <div className="border border-white/10 bg-[#111214] px-4 py-4">
                      <p className="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-stone-400">Requested</p>
                      <p className="mt-2 text-sm text-white">{selectedApplication.requested_amount_formatted || 'Not provided'}</p>
                    </div>
                    <div className="border border-white/10 bg-[#111214] px-4 py-4">
                      <p className="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-stone-400">AAC Member ID</p>
                      <p className="mt-2 text-sm text-white">{selectedApplication.aac_member_id || 'Not mapped yet'}</p>
                    </div>
                  </div>
                </div>

                <div className="grid gap-6 xl:grid-cols-[0.92fr,1.08fr]">
                  <div className="space-y-6">
                    <div className="border border-white/10 bg-[#18181b] px-5 py-5">
                      <h3 className="text-lg font-bold text-white">Reviewer Actions</h3>
                      <div className="mt-5 space-y-4">
                        <div>
                          <Label htmlFor="grant-approval-reviewer" className="text-stone-200">Assigned reviewer</Label>
                          <select
                            id="grant-approval-reviewer"
                            value={assignedReviewerId}
                            onChange={(event) => setAssignedReviewerId(Number(event.target.value))}
                            className="mt-2 h-11 w-full border border-white/10 bg-[#111214] px-3 text-sm text-white outline-none"
                          >
                            <option value={0}>Unassigned</option>
                            {reviewers.map((reviewer) => (
                              <option key={reviewer.id} value={reviewer.id}>
                                {reviewer.name}
                              </option>
                            ))}
                          </select>
                          <div className="mt-3">
                            <Button
                              type="button"
                              variant="outline"
                              className="border-stone-500 bg-transparent text-white hover:bg-white/10"
                              onClick={() => void handleReviewerSave()}
                              disabled={savingAction}
                            >
                              <UserCheck className="mr-2 h-4 w-4" />
                              Save reviewer
                            </Button>
                          </div>
                        </div>

                        <div>
                          <Label htmlFor="grant-approval-note" className="text-stone-200">Reviewer note</Label>
                          <textarea
                            id="grant-approval-note"
                            value={reviewerNote}
                            onChange={(event) => setReviewerNote(event.target.value)}
                            placeholder="Capture context, note what is missing, or explain the decision."
                            className="mt-2 min-h-[160px] w-full border border-white/10 bg-[#111214] px-4 py-3 text-sm leading-6 text-white outline-none"
                          />
                          <p className="mt-2 text-xs text-stone-400">Every workflow move writes a timeline event for the next reviewer.</p>
                        </div>

                        <div>
                          <p className="text-sm font-semibold uppercase tracking-[0.16em] text-[#f8c235]">Next step</p>
                          <div className="mt-3 flex flex-wrap gap-3">
                            {transitions.map((statusKey) => (
                              <Button
                                key={statusKey}
                                type="button"
                                className="bg-[#8f1515] text-white hover:bg-[#761111]"
                                onClick={() => void handleWorkflowAction(statusKey, false)}
                                disabled={savingAction}
                              >
                                {labels?.[statusKey] || statusKey}
                              </Button>
                            ))}
                          </div>
                        </div>

                        <div className="border border-white/10 bg-[#111214] px-4 py-4">
                          <p className="text-sm font-semibold uppercase tracking-[0.16em] text-[#f8c235]">Direct final decision</p>
                          <p className="mt-2 text-sm leading-6 text-stone-300">
                            Approve or reject immediately without walking through every intermediate step.
                          </p>
                          <div className="mt-4 flex flex-wrap gap-3">
                            {selectedApplication.workflow_status !== 'approved' ? (
                              <Button
                                type="button"
                                className="bg-[#1f6b3d] text-white hover:bg-[#195832]"
                                onClick={() => void handleWorkflowAction('approved', true)}
                                disabled={savingAction}
                              >
                                <CheckCircle2 className="mr-2 h-4 w-4" />
                                Approve now
                              </Button>
                            ) : null}
                            {selectedApplication.workflow_status !== 'rejected' ? (
                              <Button
                                type="button"
                                className="bg-[#8f1515] text-white hover:bg-[#761111]"
                                onClick={() => void handleWorkflowAction('rejected', true)}
                                disabled={savingAction}
                              >
                                <XCircle className="mr-2 h-4 w-4" />
                                Reject now
                              </Button>
                            ) : null}
                          </div>
                        </div>
                      </div>
                    </div>

                    <div className="border border-white/10 bg-[#18181b] px-5 py-5">
                      <h3 className="text-lg font-bold text-white">Workflow History</h3>
                      <div className="mt-5 space-y-4">
                        {selectedApplication.history?.length ? selectedApplication.history.map((event) => (
                          <div key={event.id} className="border-l-2 border-[#f8c235]/40 pl-4">
                            <p className="text-sm font-semibold text-white">
                              {String(event.action || 'status_changed').replace(/_/g, ' ')}
                            </p>
                            <p className="mt-1 text-xs uppercase tracking-[0.14em] text-stone-400">
                              {event.actor_name} • {formatDateTime(event.created_at)}
                            </p>
                            {(event.from_status || event.to_status) ? (
                              <p className="mt-2 text-sm text-stone-300">
                                {(labels?.[event.from_status] || 'Start')} → {(labels?.[event.to_status] || 'Now')}
                              </p>
                            ) : null}
                            {event.note ? (
                              <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-stone-200">{event.note}</p>
                            ) : null}
                          </div>
                        )) : (
                          <div className="border border-dashed border-white/10 bg-[#111214] px-4 py-6 text-sm text-stone-400">
                            No workflow history yet.
                          </div>
                        )}
                      </div>
                    </div>
                  </div>

                  <div className="border border-white/10 bg-[#18181b] px-5 py-5">
                    <div className="flex items-start gap-3">
                      <div className="bg-[#f8c235]/14 p-3 text-[#f8c235]">
                        <ClipboardList className="h-5 w-5" />
                      </div>
                      <div>
                        <h3 className="text-lg font-bold text-white">Submitted Fields</h3>
                        <p className="mt-1 text-sm leading-6 text-stone-300">
                          All normalized form fields for this application, ordered for easier human review.
                        </p>
                      </div>
                    </div>

                    <div className="mt-5 grid gap-4 md:grid-cols-2">
                      {selectedApplication.fields?.length ? selectedApplication.fields.map((field) => (
                        <div key={field.field_id || field.label} className="border border-white/10 bg-[#111214] px-4 py-4">
                          <p className="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-stone-400">
                            {field.label || 'Unnamed field'}
                          </p>
                          <div className="mt-3 whitespace-pre-wrap text-sm leading-6 text-white">
                            {field.value || '—'}
                          </div>
                        </div>
                      )) : (
                        <div className="border border-dashed border-white/10 bg-[#111214] px-4 py-6 text-sm text-stone-400">
                          No normalized submitted fields were found for this application.
                        </div>
                      )}
                    </div>
                  </div>
                </div>
              </div>
            )}
          </section>
        </div>
      </motion.div>
    </div>
  );
};

export default GrantApprovalsPage;
