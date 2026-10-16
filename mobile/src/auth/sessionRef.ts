// The signed-in person's id, readable outside React (the call engine needs it to know whether a call is for me).
let id: number | null = null;

export const setSessionUserId = (v: number | null) => {
  id = v;
};
export const getSessionUserId = () => id;
