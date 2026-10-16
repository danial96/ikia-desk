// The conversation on screen right now. A message for it needs no banner: the person is reading it.
let active: number | null = null;

export const setActiveChat = (id: number | null) => {
  active = id;
};

export const isChatOpen = (id: unknown) => active !== null && Number(id) === active;
