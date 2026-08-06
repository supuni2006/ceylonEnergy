// EDIT THIS DATA to match your real sites/projects/photos.
// Each location = one album (e.g. "Rathnapura").
// Each location has one or more projects (sub-albums, e.g. "Project 01").
// Each project has a list of photo numbers from /images/completed-projects/project-N.jpg
// The FIRST photo in each project is used as its cover image,
// and the first project's first photo is used as the location cover.

export const ALBUMS = [
  {
    name: "Rathnapura",
    projects: [
      { name: "Belihuloya project 01", photos: [1, 2, 3, 4, 5] },
      { name: "Belihuloya project 02", photos: [6, 7, 8, 9] },
      { name: "Sabaragamuwa University", photos: [36, 37, 38, 39] },
      { name: "Udawalawa project", photos: [40, 41, 42] },
    ],
  },
  {
    name: "Colombo",
    projects: [
      { name: "Project 01", photos: [10, 11, 12, 13] },
      { name: "Project 02", photos: [14, 16, 17] },
      { name: "Project 03", photos: [46, 47, 48] },
      { name: "Wellampitiya", photos: [43, 44, 45] },
    ],
  },
  {
    name: "Kandy",
    projects: [
      { name: "Project 01", photos: [18, 19, 20, 21, 22] },
      { name: "Project 02", photos: [23, 24, 25, 26, 27, 28, 29] },
    ],
  },
  {
    name: "Dehiwala",
    projects: [{ name: "Project 01", photos: [30, 31, 32] }],
  },
  {
    name: "Kaluthara",
    projects: [{ name: "Project 01", photos: [33, 34, 35] }],
  },
];

export const CP = "/images/completed-projects/";
export const photoSrc = (n) => `${CP}project-${n}.jpg`;
