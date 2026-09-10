const V3_QUESTION_COUNT = 40;
const V3_SECTION_COUNT = 10;
const V3_QUESTIONS_PER_SECTION = 4;

const V3_AREA_NAMES = {
  personal: {
    DM: "Decision-Making",
    RC: "Relationships & Connection",
    EA: "Emotional Awareness",
    CN: "Conflict Navigation",
    TI: "Trust & Intuition",
    EC: "Empathy & Compassion",
    AE: "Authentic Self-Expression",
    SP: "Stress & Pressure Response",
    VP: "Values & Life Priorities",
    CS: "Communication Style",
  },
  newjoiner: {
    DM: "Decision-Making as You Start Out",
    RC: "Building Relationships at a New Job",
    EA: "Emotional Awareness in a New Environment",
    CN: "Handling Feedback & Early Conflict",
    TI: "Trust & Intuition as a Newcomer",
    EC: "Empathy for Your New Team",
    AE: "Authentic Presence as the New Person",
    SP: "Pressure & Imposter Moments",
    VP: "What You’re Optimizing For Early On",
    CS: "Communication as a New Team Member",
  },
  manager: {
    DM: "Decision-Making",
    RC: "Team Relationships & Trust",
    EA: "Emotional Awareness at Work",
    CN: "Conflict & Difficult Conversations",
    TI: "Trust & Intuition About People",
    EC: "Empathy for Your Team",
    AE: "Authentic Leadership",
    SP: "Stress & Pressure at Work",
    VP: "What You’re Optimizing For",
    CS: "Communication as a Manager",
  },
  executive: {
    DM: "Strategic Decision-Making",
    RC: "Executive Trust & Relationships",
    EA: "Emotional Awareness in the C-Suite",
    CN: "High-Stakes Conflict & Negotiation",
    TI: "Trust & Intuition on Big Bets",
    EC: "Empathy at Scale",
    AE: "Authentic Executive Presence",
    SP: "Pressure at the Top",
    VP: "What You’re Building For",
    CS: "Communication as an Executive",
  },
};

function v3AreaName(trackKey, code, fallback = "") {
  return V3_AREA_NAMES[trackKey]?.[code] || fallback || code;
}

function v3FallbackTrack(fallbackTrack) {
  if (!fallbackTrack) return null;
  const subscales = (fallbackTrack.subscales || []).map(section => ({
    ...section,
    name: v3AreaName(fallbackTrack.key, section.code, section.name),
    items: (section.items || []).slice(0, V3_QUESTIONS_PER_SECTION),
  }));
  const allItems = subscales.flatMap((section, subIndex) => section.items.map((item, itemIndex) => ({
    ...item,
    subIndex,
    position: subIndex * V3_QUESTIONS_PER_SECTION + itemIndex + 1,
  })));
  return { ...fallbackTrack, subscales, allItems };
}

export function buildRuntimeTrack(fallbackTrack, assessment) {
  const fallbackV3 = v3FallbackTrack(fallbackTrack);
  if (!fallbackV3 || !assessment?.questions?.length) return fallbackV3;

  const fallbackSections = new Map((fallbackV3.subscales || []).map(section => [section.code, section]));
  const sectionMap = new Map();
  (assessment.sections || []).forEach(section => {
    const fallback = fallbackSections.get(section.code) || {};
    sectionMap.set(section.code, {
      code: section.code,
      name: section.name || fallback.name || v3AreaName(fallbackV3.key, section.code, section.code),
      blurb: section.description || fallback.blurb || "",
      order: Number(section.order || 0),
      items: [],
    });
  });

  assessment.questions.forEach(question => {
    const code = question.subscaleCode;
    const fallback = fallbackSections.get(code) || {};
    if (!sectionMap.has(code)) {
      sectionMap.set(code, {
        code,
        name: question.subscaleName || fallback.name || v3AreaName(fallbackV3.key, code, code),
        blurb: question.subscaleDescription || fallback.blurb || "",
        order: Number(question.sectionOrder || sectionMap.size + 1),
        items: [],
      });
    }
    sectionMap.get(code).items.push({
      id: Number(question.id),
      position: Number(question.position),
      t: question.text,
      d: question.direction,
    });
  });

  let runtimePosition = 0;
  const subscales = [...sectionMap.values()]
    .sort((left, right) => left.order - right.order)
    .map(section => ({
      code: section.code,
      name: section.name || v3AreaName(fallbackV3.key, section.code, section.code),
      blurb: section.blurb,
      items: section.items
        .sort((left, right) => left.position - right.position)
        .slice(0, V3_QUESTIONS_PER_SECTION)
        .map(item => ({ ...item, position: ++runtimePosition })),
    }));

  const allItems = subscales.flatMap((section, subIndex) => section.items.map(item => ({ ...item, subIndex })));
  if (allItems.length !== V3_QUESTION_COUNT || subscales.length !== V3_SECTION_COUNT) return fallbackV3;

  return {
    ...fallbackV3,
    subscales,
    allItems,
    answerChoices: assessment.answerChoices?.length === 5 ? assessment.answerChoices : undefined,
    assessmentVersionId: assessment.versionId,
  };
}

export function parseReportPayload(payload) {
  if (!payload) return null;
  const parse = value => {
    if (!value) return null;
    if (typeof value === "object") return value;
    try { return JSON.parse(value); } catch { return null; }
  };
  return {
    ...payload,
    free: parse(payload.free_report_json),
    paid: parse(payload.paid_report_json),
  };
}

export function reportSummary(report) {
  const free = report?.free || {};
  const summary = free.summary || {};
  return {
    profile: free.profile || "Growth Alignment",
    total: Number(free.total || 0),
    summary: typeof summary === "string" ? summary : summary.summary || "",
    strengths: Array.isArray(summary.strengths) ? summary.strengths : [],
    watchouts: Array.isArray(summary.watchouts) ? summary.watchouts : [],
    subscales: free.subscales || {},
    areaNames: free.areaNames || {},
  };
}

export { V3_AREA_NAMES, V3_QUESTION_COUNT, V3_SECTION_COUNT, V3_QUESTIONS_PER_SECTION, v3AreaName };
