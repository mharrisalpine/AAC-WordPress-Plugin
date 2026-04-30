export const BABI_YAR_PARK_SOURCES = [
  {
    label: 'Mizel Museum',
    url: 'https://mizelmuseum.org/exhibit/babi-yar-park-a-living-holocaust-memorial/',
  },
  {
    label: 'The Cultural Landscape Foundation',
    url: 'https://www.tclf.org/landscapes/babi-yar-park',
  },
];

export const BABI_YAR_PARK_CENTER = {
  lat: 39.6659,
  lng: -104.8697,
};

export const BABI_YAR_TOUR_STOPS = [
  {
    id: 'threshold',
    order: 1,
    title: 'Threshold Monoliths',
    shortTitle: 'Threshold',
    subtitle: 'Granite entry passage',
    duration: '2 min',
    mapPoint: { x: 18, y: 80 },
    coordinates: { lat: 39.66558, lng: -104.86882, radiusMeters: 42 },
    significance:
      'The memorial begins with compression and silence, asking each visitor to cross into the site intentionally.',
    history:
      'The park was completed in 1982 as a designed memorial landscape by Lawrence Halprin and Satoru Nishita. The narrow approach between dark granite monoliths marks a deliberate transition from ordinary park movement into remembrance.',
    whyItMatters:
      'This entrance establishes the tone of the tour: reflective, sober, and attentive to the lives and histories being remembered here.',
    reflection:
      'Pause before walking forward. Notice how the memorial changes your pace before it changes your view.',
    narration:
      'You are entering Babi Yar Park through the granite threshold. The design narrows the body and quiets the approach, inviting a deliberate crossing into memory, grief, and witness.',
  },
  {
    id: 'conscience-path',
    order: 2,
    title: 'Path of Conscience',
    shortTitle: 'Conscience',
    subtitle: 'Star-shaped memorial route',
    duration: '3 min',
    mapPoint: { x: 34, y: 64 },
    coordinates: { lat: 39.66592, lng: -104.86935, radiusMeters: 44 },
    significance:
      'The route geometry ties movement through the park to Jewish identity, historical memory, and public protest against genocide.',
    history:
      'Mizel Museum notes that Mayor William H. McNichols Jr. designated the land in 1969 to become a place of unified public protest. TCLF describes the memorial route as organized around a centralized pathway configured as a Star of David.',
    whyItMatters:
      'The path makes remembrance active. Visitors are not only observing history; they are being guided through a civic statement against hatred and erasure.',
    reflection:
      'As you continue, think about how memorials can protest silence as much as they preserve memory.',
    narration:
      'This route is shaped by a Star of David geometry. The park was envisioned not simply as a landscape, but as a public protest against genocide and the forgetting that allows it to return.',
  },
  {
    id: 'peoples-place',
    order: 3,
    title: "People's Place",
    shortTitle: "People's Place",
    subtitle: 'Amphitheater for gathering and dialogue',
    duration: '4 min',
    mapPoint: { x: 49, y: 48 },
    coordinates: { lat: 39.66618, lng: -104.86992, radiusMeters: 48 },
    significance:
      'This bowl-shaped gathering space holds both public ceremony and private contemplation within the memorial.',
    history:
      'TCLF identifies this area as a bowl-shaped amphitheater with a circular center platform, while Mizel Museum describes it as a place for gatherings and dialogue. The park continues to host annual remembrance ceremonies here in honor of those lost at Babi Yar and other victims of the Holocaust.',
    whyItMatters:
      'The site reminds visitors that Holocaust education is communal work. Memory survives when people gather, listen, and speak with care.',
    reflection:
      'Imagine the memorial holding both silence and shared voices. Which feels more urgent in this moment?',
    narration:
      "You have reached People's Place. This amphitheater turns remembrance into conversation, holding space for ceremony, learning, and the difficult work of speaking about crimes against humanity.",
  },
  {
    id: 'grove',
    order: 4,
    title: 'Grove of Remembrance',
    shortTitle: 'Grove',
    subtitle: 'Linden grove and water feature',
    duration: '4 min',
    mapPoint: { x: 66, y: 35 },
    coordinates: { lat: 39.66656, lng: -104.87046, radiusMeters: 45 },
    significance:
      'The grove translates loss into repetition, order, and quiet rhythm through trees, stone, and water.',
    history:
      'TCLF describes a grid of 100 linden trees with flowing water at a black granite disc in the center. The composition allows the memorial to hold immense historical loss without reducing it to a single object or inscription.',
    whyItMatters:
      'This stop offers one of the park’s clearest invitations to contemplate the scale of absence created by the Holocaust and by the massacre at Babi Yar.',
    reflection:
      'Stay for a few breaths. Let the repetition of trunks and the sound of water do the work that language cannot finish.',
    narration:
      'This is the Grove of Remembrance. The ordered lines of trees and the dark water element slow the eye, offering a place to consider how vast historical loss resists easy telling.',
  },
  {
    id: 'ravine',
    order: 5,
    title: 'Ravine Crossing',
    shortTitle: 'Ravine',
    subtitle: 'Bridge and memorial ravine',
    duration: '5 min',
    mapPoint: { x: 79, y: 54 },
    coordinates: { lat: 39.66631, lng: -104.87102, radiusMeters: 46 },
    significance:
      'The ravine and bridge are among the memorial’s most direct architectural evocations of the violence being remembered.',
    history:
      'Along the western edge of the site, TCLF notes that the ravine recalls the terrain where victims were buried in Kyiv. The narrow bridge with tall dark walls evokes the transport cars used by Nazis to move prisoners, intensifying the bodily experience of the crossing.',
    whyItMatters:
      'This stop makes the memorial’s educational purpose unmistakable. The landscape does not soften history; it asks visitors to encounter it with moral seriousness.',
    reflection:
      'Move slowly here. Notice how enclosure, height, and restricted sightlines affect your breathing and attention.',
    narration:
      'You are crossing the memorial ravine. Its bridge and dark walls echo transport and burial, drawing the body into a more immediate encounter with the violence the park remembers.',
  },
  {
    id: 'prairie',
    order: 6,
    title: 'Prairie Edge',
    shortTitle: 'Prairie',
    subtitle: 'Native plantings and restoration',
    duration: '3 min',
    mapPoint: { x: 58, y: 75 },
    coordinates: { lat: 39.66584, lng: -104.8712, radiusMeters: 50 },
    significance:
      'The memorial closes with living systems: grasses, yucca, prickly pear, and restored ecological edges that frame contemplation without ending it.',
    history:
      'TCLF records native prairie plantings around the site edges and notes a 2011 renovation by Mundus Bishop that restored prairie areas and updated walls, paving, and terraces. The living landscape keeps the memorial active rather than frozen.',
    whyItMatters:
      'This stop points toward stewardship. Remembrance is sustained not only through monuments, but through ongoing care, maintenance, and return.',
    reflection:
      'Before you leave, consider what it means for a memorial to be living, seasonal, and dependent on continued attention.',
    narration:
      'At the prairie edge, the memorial turns toward living care. Native plantings and later restoration work underscore that remembrance must be renewed, protected, and carried forward.',
  },
];

export const BABI_YAR_TOUR_INTRO = {
  eyebrow: 'Individual Mobile Tour',
  title: 'Babi Yar Park',
  subtitle: 'A living Holocaust memorial in Denver designed for quiet reflection, historical learning, and deliberate movement.',
  advisory:
    'This guide addresses genocide, antisemitism, and the Holocaust. It is designed to support a respectful self-guided visit.',
  body:
    'This prototype tour uses location-aware prompts, spoken narration, and historical context adapted from the Mizel Museum and The Cultural Landscape Foundation. It is intended for individual visitors moving through the memorial at their own pace.',
};
